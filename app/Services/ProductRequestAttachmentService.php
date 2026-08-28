<?php

namespace App\Services;

use App\Models\ProductRequest;
use App\Models\ProductRequestAttachment;
use App\Models\ProductRequestMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductRequestAttachmentService
{
    public function __construct(private TransactionalFileStorage $fileStorage) {}

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

        $disk = (string) config('product_requests.disk', 'local');
        $directory = trim((string) config('product_requests.directory', 'product-requests'), '/')
            .'/'.$productRequest->koperasi_id.'/'.now()->format('Y/m');
        $stored = [];

        foreach ($files as $file) {
            $checksum = hash_file('sha256', $file->getRealPath()) ?: null;
            $path = $this->fileStorage->store($disk, $directory, $file);
            $stored[] = $productRequest->attachments()->create([
                'message_id' => $message?->id,
                'uploaded_by' => $uploader->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'size_bytes' => max(0, (int) $file->getSize()),
                'checksum' => $checksum,
            ]);
        }

        return $stored;
    }

    public function download(ProductRequestAttachment $attachment): StreamedResponse
    {
        $filesystem = Storage::disk($attachment->disk);
        abort_unless($filesystem->exists($attachment->path), 404);

        return $filesystem->download(
            $attachment->path,
            $this->safeOriginalName($attachment->original_name),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    /** @param list<UploadedFile> $files */
    public function assertCapacity(ProductRequest $productRequest, array $files): void
    {
        $limits = config('product_requests.attachments');
        $existingCount = $productRequest->attachments()->count();
        $existingBytes = (int) $productRequest->attachments()->sum('size_bytes');
        $newBytes = array_sum(array_map(fn (UploadedFile $file) => max(0, (int) $file->getSize()), $files));

        if ($existingCount + count($files) > (int) $limits['max_files_per_request']) {
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
