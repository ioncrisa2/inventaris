<?php

namespace App\Services;

use App\Models\ProductRequest;
use App\Models\ProductRequestAttachment;
use App\Models\ProductRequestMessage;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ProductRequestAttachmentService
{
    public function __construct(
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private StoredFileAuditService $audit,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<ProductRequestAttachment>
     */
    public function store(
        ProductRequest $productRequest,
        ?ProductRequestMessage $message,
        User $uploader,
        array $files,
    ): array {
        if ($files === []) {
            return [];
        }

        if (DB::transactionLevel() === 0) {
            throw new LogicException('Lampiran request wajib disimpan di dalam transaksi database.');
        }

        if ($message !== null && (int) $message->product_request_id !== (int) $productRequest->id) {
            throw new LogicException('Pesan dan lampiran harus berasal dari request yang sama.');
        }

        $this->assertCapacity($productRequest, $files);

        $stored = [];

        foreach ($files as $file) {
            $prepared = $this->storedFiles->prepare($file, 'product_attachments');

            try {
                $registry = $this->storedFiles->persist(
                    $prepared,
                    (int) $productRequest->koperasi_id,
                    'attachment',
                    null,
                    $uploader->id,
                );
                $attachment = $productRequest->attachments()->create([
                    'message_id' => $message?->id,
                    'uploaded_by' => $uploader->id,
                    'disk' => $registry->disk,
                    'path' => $registry->path,
                    'original_name' => $prepared->originalName,
                    'mime_type' => $registry->mime_type,
                    'size_bytes' => $registry->final_size_bytes,
                    'checksum' => $registry->final_checksum_sha256,
                ]);
                $this->storedFiles->assignOwner($registry, $attachment, 'attachment');
                $stored[] = $attachment;
            } finally {
                $prepared->cleanup();
            }
        }

        return $stored;
    }

    /** @param list<string> $uuids @return list<ProductRequestAttachment> */
    public function claimTokens(
        ProductRequest $productRequest,
        ?ProductRequestMessage $message,
        User $uploader,
        array $uuids,
    ): array {
        if ($uuids === []) {
            return [];
        }
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Claim lampiran request wajib berada di dalam transaksi database.');
        }

        $this->assertCapacity($productRequest, [], $uuids);
        $attachments = [];
        foreach ($uuids as $uuid) {
            $token = StoredFile::query()->where('uuid', $uuid)->firstOrFail();
            $attachment = $productRequest->attachments()->create([
                'message_id' => $message?->id,
                'uploaded_by' => $uploader->id,
                'disk' => $token->disk ?: 'local',
                'path' => $token->path ?: 'pending/'.$token->uuid,
                'original_name' => $token->original_name,
                'mime_type' => $token->mime_type,
                'size_bytes' => $token->final_size_bytes ?: $token->source_size_bytes,
                'checksum' => $token->final_checksum_sha256 ?: $token->source_checksum_sha256,
            ]);
            $claimed = $this->asyncUploads->claim(
                $token,
                $attachment,
                $uploader,
                'product_attachments',
                'attachment',
            );
            if ($claimed->isAvailable()) {
                $this->storedFiles->syncLegacyOwner($claimed);
                $attachment->refresh();
            }
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    public function download(ProductRequestAttachment $attachment): Response
    {
        $registry = $attachment->storedFiles()->latest()->first();
        $disk = $registry?->disk ?? $attachment->disk;
        $path = $registry?->path ?? $attachment->path;
        $mime = $registry?->mime_type ?? $attachment->mime_type;
        abort_if($registry && ! $registry->isAvailable(), 423, 'File belum siap dibuka.');

        if ($registry) {
            $response = $this->storedFiles->storedFileResponse($registry, false);
            $this->audit->record($registry, 'download', auth()->user(), request());

            return $response;
        }

        $filesystem = Storage::disk($disk);
        abort_unless($filesystem->exists($path), 404);

        return $filesystem->download(
            $path,
            $this->safeOriginalName($attachment->original_name),
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    /** @param list<UploadedFile> $files */
    public function assertCapacity(ProductRequest $productRequest, array $files, array $tokenUuids = []): void
    {
        $limits = config('product_requests.attachments');
        $existingCount = $productRequest->attachments()->count();
        $existingBytes = (int) $productRequest->attachments()->sum('size_bytes');
        $newBytes = array_sum(array_map(fn (UploadedFile $file) => max(0, (int) $file->getSize()), $files));
        $tokens = $tokenUuids === []
            ? collect()
            : StoredFile::query()->whereIn('uuid', $tokenUuids)->get(['uuid', 'source_size_bytes']);
        if ($tokens->count() !== count(array_unique($tokenUuids))) {
            throw ValidationException::withMessages(['attachments_upload_uuids' => 'Token lampiran tidak valid.']);
        }
        $newBytes += (int) $tokens->sum('source_size_bytes');
        $newCount = count($files) + count($tokenUuids);

        if ($newCount > (int) $limits['max_files_per_submission']) {
            throw ValidationException::withMessages([
                'attachments' => 'Maksimal '.$limits['max_files_per_submission'].' lampiran dalam satu pengiriman.',
            ]);
        }

        if ($existingCount + $newCount > (int) $limits['max_files_per_request']) {
            throw ValidationException::withMessages([
                'attachments' => 'Satu tiket maksimal memiliki '.$limits['max_files_per_request'].' lampiran.',
            ]);
        }

        if ($existingBytes + $newBytes > (int) $limits['max_total_bytes_per_request']) {
            throw ValidationException::withMessages([
                'attachments' => 'Total ukuran lampiran dalam satu tiket maksimal 20 MB.',
            ]);
        }
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'lampiran';

        return Str::limit(trim($name) ?: 'lampiran', 200, '');
    }
}
