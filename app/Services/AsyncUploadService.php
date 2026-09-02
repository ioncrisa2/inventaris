<?php

namespace App\Services;

use App\Jobs\ScanStoredFile;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\ProductRequestAttachment;
use App\Models\RiwayatKaryawan;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\UploadPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AsyncUploadService
{
    public function __construct(
        private TransactionalFileStorage $storage,
        private StoredFileService $storedFiles,
        private StoredFileAuditService $audit,
        private MediaPipelineService $pipeline,
    ) {}

    public function stage(
        UploadedFile $uploaded,
        string $policy,
        User $actor,
        ?Request $request = null,
        ?int $targetKoperasiId = null,
    ): StoredFile {
        UploadPolicy::get($policy);
        $koperasiId = $actor->koperasi_id !== null ? (int) $actor->koperasi_id : $targetKoperasiId;
        if ($actor->koperasi_id === null && ! $actor->isSystemOwner()) {
            throw new \DomainException('Upload hanya tersedia untuk pengguna koperasi.');
        }
        if (! $koperasiId || ! Koperasi::query()->whereKey($koperasiId)->exists()) {
            throw new \DomainException('Koperasi tujuan upload tidak tersedia.');
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $mime = strtolower((string) ($uploaded->getMimeType() ?: 'application/octet-stream'));
        $sourcePath = $uploaded->getRealPath();
        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw new \RuntimeException('File upload tidak dapat dibaca.');
        }
        $checksum = hash_file('sha256', $sourcePath);
        if (! is_string($checksum)) {
            throw new \RuntimeException('Checksum file upload tidak dapat dihitung.');
        }
        $dimensions = str_starts_with($mime, 'image/') ? @getimagesize($sourcePath) : false;
        $scanRequired = (bool) config('uploads.features.scan_required', false);
        $stagingPath = 'staging/'.$koperasiId.'/'.now()->format('Y/m').'/'.$uuid.'.'.$extension;

        $file = DB::transaction(function () use (
            $uploaded,
            $policy,
            $actor,
            $request,
            $uuid,
            $extension,
            $mime,
            $sourcePath,
            $checksum,
            $dimensions,
            $scanRequired,
            $stagingPath,
            $koperasiId,
        ) {
            $this->storage->putFilePath('local', $stagingPath, $sourcePath);
            $file = StoredFile::query()->create([
                'uuid' => $uuid,
                'koperasi_id' => $koperasiId,
                'uploaded_by' => $actor->id,
                'policy' => $policy,
                'collection' => 'staging',
                'status' => $scanRequired ? 'pending_scan' : 'processing',
                'scan_status' => $scanRequired ? 'pending' : 'not_required',
                'staging_disk' => 'local',
                'staging_path' => $stagingPath,
                'original_name' => $this->safeName($uploaded->getClientOriginalName()),
                'mime_type' => $mime,
                'extension' => $extension,
                'source_size_bytes' => max(0, (int) $uploaded->getSize()),
                'source_checksum_sha256' => $checksum,
                'width' => is_array($dimensions) ? (int) $dimensions[0] : null,
                'height' => is_array($dimensions) ? (int) $dimensions[1] : null,
                'expires_at' => now()->addHours((int) config('uploads.staging_expiry_hours', 24)),
            ]);
            $this->audit->record($file, 'upload', $actor, $request, $policy);

            return $file;
        }, 3);

        if ($scanRequired) {
            ScanStoredFile::dispatch($file);
        } else {
            $this->pipeline->continue($file);
        }

        return $file->refresh();
    }

    public function claim(
        StoredFile $file,
        Model $owner,
        User $actor,
        string $expectedPolicy,
        string $collection,
        ?Request $request = null,
    ): StoredFile {
        $claimed = DB::transaction(function () use ($file, $owner, $actor, $expectedPolicy, $collection, $request) {
            $locked = StoredFile::query()->lockForUpdate()->findOrFail($file->id);
            abort_unless((int) $locked->uploaded_by === (int) $actor->id, 403);
            if ($actor->koperasi_id !== null) {
                abort_unless((int) $locked->koperasi_id === (int) $actor->koperasi_id, 403);
            } else {
                abort_unless($actor->isSystemOwner(), 403);
            }
            abort_unless($this->ownerKoperasiId($owner) === (int) $locked->koperasi_id, 403);
            abort_unless(hash_equals($expectedPolicy, $locked->policy), 422, 'Policy token upload tidak sesuai.');
            abort_if($locked->expires_at?->isPast() && $locked->claimed_at === null, 410, 'Token upload sudah kedaluwarsa.');

            if ($locked->owner_id !== null) {
                abort_unless(
                    $locked->owner_type === $owner->getMorphClass() && (int) $locked->owner_id === (int) $owner->getKey(),
                    409,
                    'Token upload sudah digunakan.',
                );

                return $locked;
            }

            $this->storedFiles->assignOwner($locked, $owner, $collection);
            $this->audit->record($locked, 'claim', $actor, $request, $collection);

            return $locked->refresh();
        }, 3);

        if ($claimed->policy === 'calendar_import' && $claimed->status === 'processing') {
            $this->pipeline->continue($claimed);
        }

        return $claimed;
    }

    public function cancel(StoredFile $file, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($file, $actor, $request): void {
            $locked = StoredFile::query()->with('variants')->lockForUpdate()->findOrFail($file->id);
            abort_unless((int) $locked->uploaded_by === (int) $actor->id, 403);
            if ($actor->koperasi_id !== null) {
                abort_unless((int) $locked->koperasi_id === (int) $actor->koperasi_id, 403);
            } else {
                abort_unless($actor->isSystemOwner(), 403);
            }
            abort_if($locked->claimed_at !== null, 409, 'File yang sudah diklaim tidak dapat dibatalkan dari upload sementara.');

            if ($locked->staging_disk && $locked->staging_path) {
                $this->storage->deleteAfterCommit($locked->staging_disk, $locked->staging_path);
            }
            foreach ($locked->variants as $variant) {
                $this->storage->deleteAfterCommit($variant->disk, $variant->path);
            }
            $locked->variants()->delete();
            $locked->forceFill([
                'status' => 'canceled',
                'staging_disk' => null,
                'staging_path' => null,
                'disk' => null,
                'path' => null,
                'failure_code' => 'canceled_by_user',
                'expires_at' => now(),
            ])->save();
            $this->audit->record($locked, 'cancel', $actor, $request);
        }, 3);
    }

    /** @return array<string,mixed> */
    public function statusPayload(StoredFile $file): array
    {
        return [
            'uuid' => $file->uuid,
            'status' => $file->status,
            'scan_status' => $file->scan_status,
            'name' => $file->original_name,
            'mime' => $file->mime_type,
            'size' => $file->source_size_bytes,
            'expires_at' => $file->expires_at?->toIso8601String(),
            'failure_code' => $file->failure_code,
            'metadata' => $file->metadata,
            'preview_url' => $file->isAvailable() ? route('uploads.preview', $file) : null,
            'download_url' => $file->isAvailable() ? route('uploads.download', $file) : null,
        ];
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'file';

        return Str::limit(trim($name) ?: 'file', 200, '');
    }

    private function ownerKoperasiId(Model $owner): ?int
    {
        $direct = $owner->getAttribute('koperasi_id');
        if ($direct !== null) {
            return (int) $direct;
        }

        return match (true) {
            $owner instanceof Koperasi => (int) $owner->getKey(),
            $owner instanceof FotoBarang, $owner instanceof DokumenBarang => (int) $owner->barang?->koperasi_id,
            $owner instanceof DokumenKaryawan => (int) $owner->karyawan?->koperasi_id,
            $owner instanceof RiwayatKaryawan => (int) $owner->karyawan?->koperasi_id,
            $owner instanceof DokumenRiwayatKaryawan => (int) $owner->riwayat?->karyawan?->koperasi_id,
            $owner instanceof ProductRequestAttachment => (int) DB::table('product_requests')
                ->where('id', $owner->product_request_id)
                ->value('koperasi_id'),
            $owner instanceof Barang, $owner instanceof Karyawan => (int) $owner->koperasi_id,
            default => null,
        };
    }
}
