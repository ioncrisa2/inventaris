<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdentitasAplikasiService
{
    private const KEY_NAMA = 'identitas_nama';

    private const KEY_ALAMAT = 'identitas_alamat';

    private const KEY_LOGO_PATH = 'identitas_logo_path';

    public function __construct(private TransactionalFileStorage $fileStorage) {}

    public function nama(): string
    {
        return Pengaturan::get(self::KEY_NAMA) ?? config('app.name');
    }

    public function alamat(): ?string
    {
        return Pengaturan::get(self::KEY_ALAMAT);
    }

    public function logoPath(): ?string
    {
        return Pengaturan::get(self::KEY_LOGO_PATH);
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @param  array{nama: string, alamat: ?string, logo: ?UploadedFile}  $data
     */
    public function simpan(array $data): void
    {
        DB::transaction(function () use ($data) {
            $logoLama = null;

            if ($data['logo'] instanceof UploadedFile) {
                $logoLama = $this->logoPath();
                Pengaturan::set(self::KEY_LOGO_PATH, $this->fileStorage->store('public', 'identitas', $data['logo']));
            }

            Pengaturan::set(self::KEY_NAMA, $data['nama']);
            Pengaturan::set(self::KEY_ALAMAT, (string) ($data['alamat'] ?? ''));

            $this->fileStorage->deleteAfterCommit('public', $logoLama);
        }, 3);
    }
}
