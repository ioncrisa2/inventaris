<?php

namespace App\Rules;

use App\Contracts\VirusScanner;
use App\Models\StoredFile;
use App\Services\StoredFileAuditService;
use App\Support\UploadPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

final class ValidUploadFile implements ValidationRule
{
    public function __construct(private readonly string $policy) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('File gagal diunggah. Silakan pilih ulang file.');

            return;
        }

        $policy = UploadPolicy::get($this->policy);
        $extension = strtolower($value->getClientOriginalExtension());
        $mime = strtolower((string) $value->getMimeType());
        $allowed = $policy['mime_by_extension'];

        if (! array_key_exists($extension, $allowed)) {
            $fail('Format file tidak didukung. Gunakan '.strtoupper(implode(', ', $policy['extensions'])).'.');

            return;
        }

        if (! in_array($mime, (array) $allowed[$extension], true)) {
            $fail('Ekstensi file tidak sesuai dengan tipe isi file yang terdeteksi.');

            return;
        }

        $maxBytes = (int) $policy['max_file_kilobytes'] * 1024;
        if ((int) $value->getSize() > $maxBytes) {
            $fail('Ukuran file maksimal '.$this->formatMegabytes($maxBytes).'.');

            return;
        }

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($value->getRealPath());
            if ($dimensions === false) {
                $fail('Gambar rusak atau tidak dapat dibaca.');

                return;
            }

            [$width, $height] = $dimensions;
            $maxDimension = (int) config('uploads.max_image_dimension', 8000);
            $maxMegapixels = (int) config('uploads.max_image_megapixels', 40);

            if ($width > $maxDimension || $height > $maxDimension) {
                $fail("Dimensi gambar maksimal {$maxDimension} piksel pada setiap sisi.");

                return;
            }

            if (($width * $height) > ($maxMegapixels * 1_000_000)) {
                $fail("Resolusi gambar maksimal {$maxMegapixels} megapiksel.");

                return;
            }
        }

        $this->scanMultipartFallback($value, $fail);
    }

    private function scanMultipartFallback(UploadedFile $file, Closure $fail): void
    {
        if (! (bool) config('uploads.features.scan_required', false) || request()->routeIs('uploads.store')) {
            return;
        }
        $stream = @fopen($file->getRealPath(), 'rb');
        if (! is_resource($stream)) {
            $fail('File tidak dapat dibaca untuk pemindaian antivirus.');

            return;
        }

        try {
            $result = app(VirusScanner::class)->scan($stream);
        } catch (Throwable) {
            $fail('Pemindai antivirus belum tersedia. File belum disimpan; silakan coba lagi.');

            return;
        } finally {
            fclose($stream);
        }

        if ($result === 'infected') {
            $actor = auth()->user();
            $targetTenant = $actor?->koperasi_id ?? request()->integer('koperasi_id');
            $auditFile = new StoredFile([
                'uuid' => (string) Str::uuid(),
                'koperasi_id' => $targetTenant ?: null,
            ]);
            app(StoredFileAuditService::class)->record(
                $auditFile,
                'scan_rejected',
                $actor,
                request(),
                $this->policy,
            );
            $fail('File ditolak karena terdeteksi berbahaya.');
        } elseif ($result !== 'clean') {
            $fail('Hasil pemindaian antivirus tidak dapat diverifikasi.');
        }
    }

    private function formatMegabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', '.'), '0'), ',').' MB';
    }
}
