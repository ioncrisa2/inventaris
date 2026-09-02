<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\ProductRequestAttachment;
use App\Models\StoredFile;
use App\Support\PreparedUpload;
use App\Support\UploadPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StoredFileService
{
    private ImageManager $images;

    public function __construct(
        private TransactionalFileStorage $storage,
        private StoredFileAuditService $audit,
    ) {
        $this->images = new ImageManager(new Driver, strip: true);
    }

    public function prepare(UploadedFile $file, string $policy): PreparedUpload
    {
        UploadPolicy::get($policy);
        $sourcePath = $file->getRealPath();
        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw ValidationException::withMessages(['file' => 'File sumber tidak dapat dibaca.']);
        }

        $sourceMime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $sourceExtension = strtolower($file->getClientOriginalExtension());
        $sourceChecksum = hash_file('sha256', $sourcePath);
        if (! is_string($sourceChecksum)) {
            throw new \RuntimeException('Checksum file tidak dapat dihitung.');
        }

        if (in_array($policy, ['employee_photo', 'asset_photo', 'asset_gallery', 'logo'], true)) {
            return $this->prepareImage(
                $file,
                $policy,
                $sourcePath,
                $sourceMime,
                $sourceExtension,
                $sourceChecksum,
            );
        }

        $temporaryPath = $this->temporaryCopy($sourcePath);
        $dimensions = str_starts_with($sourceMime, 'image/') ? @getimagesize($temporaryPath) : false;

        return new PreparedUpload(
            uuid: (string) Str::uuid(),
            policy: $policy,
            originalName: $this->safeOriginalName($file->getClientOriginalName()),
            sourceMimeType: $sourceMime,
            sourceExtension: $sourceExtension,
            sourceSizeBytes: max(0, (int) $file->getSize()),
            sourceChecksumSha256: $sourceChecksum,
            sourceWidth: is_array($dimensions) ? (int) $dimensions[0] : null,
            sourceHeight: is_array($dimensions) ? (int) $dimensions[1] : null,
            variants: [
                'canonical' => $this->variantMetadata(
                    $temporaryPath,
                    true,
                    $sourceMime,
                    $sourceExtension,
                    is_array($dimensions) ? (int) $dimensions[0] : null,
                    is_array($dimensions) ? (int) $dimensions[1] : null,
                ),
            ],
        );
    }

    /** @param list<UploadedFile> $files @return list<PreparedUpload> */
    public function prepareMany(array $files, string $policy): array
    {
        $prepared = [];

        try {
            foreach ($files as $file) {
                $prepared[] = $this->prepare($file, $policy);
            }
        } catch (Throwable $exception) {
            foreach ($prepared as $upload) {
                $upload->cleanup();
            }
            throw $exception;
        }

        return $prepared;
    }

    public function persist(
        PreparedUpload $prepared,
        int $koperasiId,
        string $collection,
        ?Model $owner = null,
        ?int $uploadedBy = null,
    ): StoredFile {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Registry file wajib disimpan di dalam transaksi database.');
        }

        $disk = $this->diskFor($prepared->policy);
        $directory = 'tenants/'.$koperasiId.'/'.$prepared->policy.'/'.now()->format('Y/m');
        $storedVariants = [];

        foreach ($prepared->variants as $name => $variant) {
            $suffix = $name === 'canonical' ? '' : '_'.$name;
            $path = $directory.'/'.$prepared->uuid.$suffix.'.'.$variant['extension'];
            $this->storage->putFilePath($disk, $path, $variant['path']);
            $storedVariants[$name] = [...$variant, 'disk' => $disk, 'path' => $path];
        }

        $canonical = $storedVariants['canonical'];
        $storedFile = new StoredFile([
            'uuid' => $prepared->uuid,
            'koperasi_id' => $koperasiId,
            'uploaded_by' => $uploadedBy,
            'policy' => $prepared->policy,
            'collection' => $collection,
            'status' => 'ready',
            'scan_status' => (bool) config('uploads.features.scan_required', false) ? 'clean' : 'not_required',
            'disk' => $disk,
            'path' => $canonical['path'],
            'original_name' => $prepared->originalName,
            'mime_type' => $canonical['mime_type'],
            'extension' => $canonical['extension'],
            'source_size_bytes' => $prepared->sourceSizeBytes,
            'final_size_bytes' => $canonical['size_bytes'],
            'source_checksum_sha256' => $prepared->sourceChecksumSha256,
            'final_checksum_sha256' => $canonical['checksum_sha256'],
            'width' => $canonical['width'],
            'height' => $canonical['height'],
            'claimed_at' => $owner ? now() : null,
            'processed_at' => now(),
            'scanned_at' => (bool) config('uploads.features.scan_required', false) ? now() : null,
        ]);
        if ($owner) {
            $storedFile->owner()->associate($owner);
        }
        $storedFile->save();

        if ($storedFile->scan_status === 'clean') {
            $this->audit->record($storedFile, 'scan_clean', auth()->user(), app()->bound('request') ? request() : null, 'multipart_fallback');
        }

        foreach ($storedVariants as $name => $variant) {
            $storedFile->variants()->create([
                'name' => $name,
                'disk' => $variant['disk'],
                'path' => $variant['path'],
                'mime_type' => $variant['mime_type'],
                'extension' => $variant['extension'],
                'size_bytes' => $variant['size_bytes'],
                'checksum_sha256' => $variant['checksum_sha256'],
                'width' => $variant['width'],
                'height' => $variant['height'],
            ]);
        }

        return $storedFile;
    }

    public function assignOwner(StoredFile $file, Model $owner, string $collection): StoredFile
    {
        $file->owner()->associate($owner);
        $file->forceFill(['collection' => $collection, 'claimed_at' => $file->claimed_at ?? now()])->save();
        if ($file->isAvailable()) {
            $this->syncLegacyOwner($file);
        }

        return $file;
    }

    public function finalizeStagedImage(StoredFile $file, PreparedUpload $prepared): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Finalisasi gambar wajib berada di dalam transaksi database.');
        }
        if ($file->policy !== $prepared->policy || blank($file->staging_path) || blank($file->staging_disk)) {
            throw new \LogicException('Data staging gambar tidak valid.');
        }

        $disk = $this->diskFor($file->policy);
        $directory = 'tenants/'.$file->koperasi_id.'/'.$file->policy.'/'.now()->format('Y/m');
        $storedVariants = [];

        foreach ($prepared->variants as $name => $variant) {
            $suffix = $name === 'canonical' ? '' : '_'.$name;
            $path = $directory.'/'.$file->uuid.$suffix.'.'.$variant['extension'];
            $this->storage->putFilePath($disk, $path, $variant['path']);
            $storedVariants[$name] = [...$variant, 'disk' => $disk, 'path' => $path];
        }

        $canonical = $storedVariants['canonical'];
        $stagingDisk = $file->staging_disk;
        $stagingPath = $file->staging_path;
        $file->forceFill([
            'status' => 'ready',
            'disk' => $disk,
            'path' => $canonical['path'],
            'mime_type' => $canonical['mime_type'],
            'extension' => $canonical['extension'],
            'final_size_bytes' => $canonical['size_bytes'],
            'final_checksum_sha256' => $canonical['checksum_sha256'],
            'width' => $canonical['width'],
            'height' => $canonical['height'],
            'staging_disk' => null,
            'staging_path' => null,
            'failure_code' => null,
            'processed_at' => now(),
        ])->save();

        foreach ($storedVariants as $name => $variant) {
            $file->variants()->updateOrCreate(['name' => $name], [
                'disk' => $variant['disk'],
                'path' => $variant['path'],
                'mime_type' => $variant['mime_type'],
                'extension' => $variant['extension'],
                'size_bytes' => $variant['size_bytes'],
                'checksum_sha256' => $variant['checksum_sha256'],
                'width' => $variant['width'],
                'height' => $variant['height'],
            ]);
        }

        $this->storage->deleteAfterCommit($stagingDisk, $stagingPath);
        $this->syncLegacyOwner($file);

        return $file->refresh();
    }

    public function finalizeStagedDocument(StoredFile $file, bool $ready = true): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Finalisasi dokumen wajib berada di dalam transaksi database.');
        }
        if (blank($file->staging_path) || blank($file->staging_disk)) {
            return $file;
        }

        $disk = 'local';
        $path = 'tenants/'.$file->koperasi_id.'/'.$file->policy.'/'.now()->format('Y/m').'/'.$file->uuid.'.'.$file->extension;
        $this->storage->move($file->staging_disk, $file->staging_path, $path);
        $file->forceFill([
            'status' => $ready ? 'ready' : 'processing',
            'disk' => $disk,
            'path' => $path,
            'final_size_bytes' => $file->source_size_bytes,
            'final_checksum_sha256' => $file->source_checksum_sha256,
            'staging_disk' => null,
            'staging_path' => null,
            'failure_code' => null,
            'processed_at' => $ready ? now() : null,
        ])->save();
        $file->variants()->updateOrCreate(['name' => 'canonical'], [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->mime_type,
            'extension' => $file->extension,
            'size_bytes' => $file->source_size_bytes,
            'checksum_sha256' => $file->source_checksum_sha256,
            'width' => $file->width,
            'height' => $file->height,
        ]);
        $this->syncLegacyOwner($file);

        return $file->refresh();
    }

    public function storeGeneratedVariant(
        StoredFile $file,
        string $name,
        string $sourcePath,
        string $mime,
        string $extension,
        ?int $width,
        ?int $height,
    ): void {
        if (DB::transactionLevel() === 0 || blank($file->disk) || blank($file->path)) {
            throw new \LogicException('Penyimpanan varian wajib berada di dalam transaksi finalisasi.');
        }

        $path = pathinfo($file->path, PATHINFO_DIRNAME).'/'.$file->uuid.'_'.$name.'.'.$extension;
        $this->storage->putFilePath($file->disk, $path, $sourcePath);
        $metadata = $this->variantMetadata($sourcePath, false, $mime, $extension, $width, $height);
        $file->variants()->updateOrCreate(['name' => $name], [
            'disk' => $file->disk,
            'path' => $path,
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => $metadata['size_bytes'],
            'checksum_sha256' => $metadata['checksum_sha256'],
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function markReady(StoredFile $file, array $metadata = []): StoredFile
    {
        $file->forceFill([
            'status' => 'ready',
            'failure_code' => null,
            'processed_at' => now(),
            'metadata' => $metadata === [] ? $file->metadata : array_merge($file->metadata ?? [], $metadata),
        ])->save();
        $this->syncLegacyOwner($file);

        return $file;
    }

    public function syncLegacyOwner(StoredFile $file): void
    {
        $owner = $file->owner;
        if (! $owner || blank($file->path)) {
            return;
        }

        if ($owner instanceof Barang && $file->collection === 'foto_sampul') {
            $owner->forceFill(['foto_sampul' => $file->path])->save();
        } elseif ($owner instanceof Karyawan && $file->collection === 'foto_karyawan') {
            $owner->forceFill(['foto_karyawan' => $file->path])->save();
        } elseif ($owner instanceof FotoBarang) {
            $owner->forceFill(['path' => $file->path])->save();
        } elseif ($owner instanceof DokumenBarang || $owner instanceof DokumenKaryawan || $owner instanceof DokumenRiwayatKaryawan) {
            $owner->forceFill(['path' => $file->path])->save();
        } elseif ($owner instanceof ProductRequestAttachment) {
            $owner->forceFill([
                'disk' => $file->disk,
                'path' => $file->path,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->final_size_bytes,
                'checksum' => $file->final_checksum_sha256,
            ])->save();
        } elseif ($owner instanceof Koperasi && $file->collection === 'logo') {
            $existing = DB::table('pengaturan')
                ->where('koperasi_id', $owner->id)
                ->where('key', 'identitas_logo_path')
                ->exists();
            if ($existing) {
                DB::table('pengaturan')
                    ->where('koperasi_id', $owner->id)
                    ->where('key', 'identitas_logo_path')
                    ->update(['value' => $file->path, 'updated_at' => now()]);
            } else {
                DB::table('pengaturan')->insert([
                    'koperasi_id' => $owner->id,
                    'key' => 'identitas_logo_path',
                    'value' => $file->path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function latestForOwner(Model $owner, ?string $collection = null): ?StoredFile
    {
        $query = $owner->storedFiles()->with('variants')->latest('id');
        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        return $query->first();
    }

    public function privateResponse(
        Model $owner,
        string $fallbackDisk,
        string $fallbackPath,
        string $originalName,
        ?string $collection = null,
        bool $download = false,
    ): Response {
        $registry = $this->latestForOwner($owner, $collection);
        abort_if($registry && ! $registry->isAvailable(), 423, 'File belum siap dibuka.');

        $disk = $registry?->disk ?? $fallbackDisk;
        $path = $registry?->path ?? $fallbackPath;
        $mime = $registry?->mime_type;
        $filesystem = Storage::disk($disk);
        abort_unless($filesystem->exists($path), 404);
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
        if ($mime) {
            $headers['Content-Type'] = $mime;
        }

        $name = $this->safeOriginalName($registry?->original_name ?? $originalName);

        if ($registry) {
            $this->audit->record($registry, $download ? 'download' : 'preview', auth()->user(), request());
            if ((bool) config('uploads.features.x_accel', false) && $disk === 'local') {
                abort_unless($this->isSafeStoragePath($path), 404);
                $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
                $disposition = HeaderUtils::makeDisposition(
                    $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                    $name,
                    Str::ascii($name) ?: 'file',
                );
                $headers['Content-Disposition'] = $disposition;
                $headers['X-Accel-Redirect'] = '/protected-files/'.$encoded;
                if (! $download) {
                    $headers['Content-Security-Policy'] = "sandbox; default-src 'none'";
                }

                return response('', 200, $headers);
            }
        }

        return $download
            ? $filesystem->download($path, $name, $headers)
            : $filesystem->response($path, $name, $headers);
    }

    public function storedFileResponse(StoredFile $file, bool $preview = false): Response
    {
        abort_unless($file->isAvailable(), 423, 'File belum siap dibuka.');
        abort_if(
            $preview && $file->mime_type !== 'application/pdf' && ! str_starts_with($file->mime_type, 'image/'),
            404,
        );

        $variant = $preview && $file->mime_type === 'application/pdf'
            ? $file->variants()->where('name', 'preview')->first()
            : $file->variants()->where('name', 'canonical')->first();
        abort_unless($variant, 404);
        $disk = $variant->disk;
        $path = $variant->path;
        $mime = $variant->mime_type;
        $filesystem = Storage::disk($disk);
        abort_unless($filesystem->exists($path), 404);

        $download = ! $preview;
        $name = $download ? $this->safeOriginalName($file->original_name) : basename($path);
        $disposition = HeaderUtils::makeDisposition(
            $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $name,
            Str::ascii($name) ?: 'file',
        );
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
        if ($preview) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'";
        }

        if ((bool) config('uploads.features.x_accel', false) && $disk === 'local') {
            abort_unless($this->isSafeStoragePath($path), 404);
            $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
            $headers['X-Accel-Redirect'] = '/protected-files/'.$encoded;

            return response('', 200, $headers);
        }

        return $download
            ? $filesystem->download($path, $name, $headers)
            : $filesystem->response($path, $name, $headers);
    }

    public function registerLegacy(
        Model $owner,
        int $koperasiId,
        string $policy,
        string $collection,
        string $disk,
        string $path,
        ?string $originalName = null,
        ?int $uploadedBy = null,
    ): StoredFile {
        UploadPolicy::get($policy);

        $existing = $owner->storedFiles()
            ->where('collection', $collection)
            ->where('disk', $disk)
            ->where('path', $path)
            ->first();
        if ($existing) {
            return $existing;
        }

        $filesystem = Storage::disk($disk);
        if (! $filesystem->exists($path)) {
            throw new \RuntimeException('File legacy tidak ditemukan.');
        }

        $stream = $filesystem->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('File legacy tidak dapat dibaca.');
        }

        try {
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) === false) {
                throw new \RuntimeException('Checksum file legacy tidak dapat dihitung.');
            }
            $checksum = hash_final($hash);
        } finally {
            fclose($stream);
        }

        $size = $filesystem->size($path);
        $mime = strtolower((string) ($filesystem->mimeType($path) ?: 'application/octet-stream'));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';
        [$width, $height] = $this->legacyDimensions($disk, $path, $mime);

        return DB::transaction(function () use (
            $owner,
            $koperasiId,
            $policy,
            $collection,
            $disk,
            $path,
            $originalName,
            $uploadedBy,
            $checksum,
            $size,
            $mime,
            $extension,
            $width,
            $height,
        ) {
            $storedFile = new StoredFile([
                'uuid' => (string) Str::uuid(),
                'koperasi_id' => $koperasiId,
                'uploaded_by' => $uploadedBy,
                'policy' => $policy,
                'collection' => $collection,
                'status' => 'ready',
                'scan_status' => 'not_required',
                'disk' => $disk,
                'path' => $path,
                'original_name' => $this->safeOriginalName($originalName ?: basename($path)),
                'mime_type' => $mime,
                'extension' => $extension,
                'source_size_bytes' => (int) $size,
                'final_size_bytes' => (int) $size,
                'source_checksum_sha256' => $checksum,
                'final_checksum_sha256' => $checksum,
                'width' => $width,
                'height' => $height,
                'claimed_at' => now(),
                'processed_at' => now(),
            ]);
            $storedFile->owner()->associate($owner);
            $storedFile->save();
            $storedFile->variants()->create([
                'name' => 'canonical',
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $mime,
                'extension' => $extension,
                'size_bytes' => (int) $size,
                'checksum_sha256' => $checksum,
                'width' => $width,
                'height' => $height,
            ]);

            return $storedFile;
        });
    }

    public function deleteForOwner(Model $owner, ?string $collection = null, string $event = 'delete'): void
    {
        $query = $owner->storedFiles();
        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        foreach ($query->with('variants')->get() as $storedFile) {
            $this->audit->record($storedFile, $event, auth()->user(), app()->bound('request') ? request() : null);
            if ($storedFile->staging_disk && $storedFile->staging_path) {
                $this->storage->deleteAfterCommit($storedFile->staging_disk, $storedFile->staging_path);
            }
            foreach ($storedFile->variants as $variant) {
                $this->storage->deleteAfterCommit($variant->disk, $variant->path);
            }
            if ($storedFile->disk && $storedFile->path && ! $storedFile->variants->contains('path', $storedFile->path)) {
                $this->storage->deleteAfterCommit($storedFile->disk, $storedFile->path);
            }
            $storedFile->delete();
        }
    }

    private function prepareImage(
        UploadedFile $file,
        string $policy,
        string $sourcePath,
        string $sourceMime,
        string $sourceExtension,
        string $sourceChecksum,
    ): PreparedUpload {
        try {
            $probe = $this->images->decodePath($sourcePath)->orient();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'Gambar rusak atau tidak dapat diproses.']);
        }

        if ($probe->isAnimated()) {
            throw ValidationException::withMessages(['file' => 'Gambar animasi tidak didukung.']);
        }

        $sourceWidth = $probe->width();
        $sourceHeight = $probe->height();
        $quality = $policy === 'logo' ? 90 : 82;
        $master = $this->images->decodePath($sourcePath)->orient();

        if ($policy === 'employee_photo') {
            $master->coverDown(640, 640);
        } elseif ($policy === 'logo') {
            $master->scaleDown(width: 1024, height: 1024);
        } else {
            $master->scaleDown(width: 2048, height: 2048);
        }

        $canonical = null;
        $thumbnailVariant = null;

        try {
            $canonical = $this->temporaryWebp($master, $quality);
            $thumbnail = $this->images->decodePath($canonical['path']);
            if ($policy === 'employee_photo') {
                $thumbnail->coverDown(160, 160);
            } else {
                $thumbnail->scaleDown(width: 320, height: 320);
            }
            $thumbnailVariant = $this->temporaryWebp($thumbnail, $quality);
        } catch (Throwable $exception) {
            foreach ([$canonical, $thumbnailVariant] as $variant) {
                if (is_array($variant) && ($variant['temporary'] ?? false) && is_file($variant['path'])) {
                    @unlink($variant['path']);
                }
            }

            throw $exception;
        }

        return new PreparedUpload(
            uuid: (string) Str::uuid(),
            policy: $policy,
            originalName: $this->safeOriginalName($file->getClientOriginalName()),
            sourceMimeType: $sourceMime,
            sourceExtension: $sourceExtension,
            sourceSizeBytes: max(0, (int) $file->getSize()),
            sourceChecksumSha256: $sourceChecksum,
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            variants: ['canonical' => $canonical, 'thumbnail' => $thumbnailVariant],
        );
    }

    /** @return array{path:string, temporary:bool, mime_type:string, extension:string, size_bytes:int, checksum_sha256:string, width:int, height:int} */
    private function temporaryWebp(ImageInterface $image, int $quality): array
    {
        $path = tempnam(sys_get_temp_dir(), 'stored-image-');
        if (! is_string($path)) {
            throw new \RuntimeException('File temporary gambar tidak dapat dibuat.');
        }

        try {
            $encoded = $image->encode(new WebpEncoder(quality: $quality, strip: true));
            if (file_put_contents($path, (string) $encoded) === false) {
                throw new \RuntimeException('Hasil gambar tidak dapat ditulis.');
            }

            return $this->variantMetadata($path, true, 'image/webp', 'webp', $image->width(), $image->height());
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function temporaryCopy(string $sourcePath): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stored-file-');
        if (! is_string($path)) {
            throw new \RuntimeException('File temporary tidak dapat dibuat.');
        }

        $source = @fopen($sourcePath, 'rb');
        $target = @fopen($path, 'wb');
        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            @unlink($path);

            throw new \RuntimeException('File temporary tidak dapat disalin.');
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new \RuntimeException('File temporary tidak dapat disalin.');
            }
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        } finally {
            fclose($source);
            fclose($target);
        }

        return $path;
    }

    /** @return array{path:string, temporary:bool, mime_type:string, extension:string, size_bytes:int, checksum_sha256:string, width:?int, height:?int} */
    private function variantMetadata(
        string $path,
        bool $temporary,
        string $mime,
        string $extension,
        ?int $width,
        ?int $height,
    ): array {
        $size = filesize($path);
        $checksum = hash_file('sha256', $path);
        if (! is_int($size) || ! is_string($checksum)) {
            throw new \RuntimeException('Metadata file tidak dapat dihitung.');
        }

        return [
            'path' => $path,
            'temporary' => $temporary,
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function diskFor(string $policy): string
    {
        return in_array($policy, ['employee_photo', 'asset_photo', 'asset_gallery', 'logo'], true)
            ? 'public'
            : 'local';
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'file';

        return Str::limit(trim($name) ?: 'file', 200, '');
    }

    private function isSafeStoragePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return $normalized !== ''
            && ! str_starts_with($normalized, '/')
            && ! str_contains($normalized, "\0")
            && ! collect(explode('/', $normalized))->contains(
                fn (string $segment): bool => in_array($segment, ['', '.', '..'], true),
            );
    }

    /** @return array{0:?int,1:?int} */
    private function legacyDimensions(string $disk, string $path, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        try {
            $dimensions = @getimagesize(Storage::disk($disk)->path($path));

            return is_array($dimensions)
                ? [(int) $dimensions[0], (int) $dimensions[1]]
                : [null, null];
        } catch (Throwable) {
            return [null, null];
        }
    }
}
