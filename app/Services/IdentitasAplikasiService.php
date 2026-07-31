<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdentitasAplikasiService
{
    private const KEY_NAMA = 'identitas_nama';

    private const KEY_ALAMAT = 'identitas_alamat';

    private const KEY_LOGO_PATH = 'identitas_logo_path';

    public function __construct(private TransactionalFileStorage $fileStorage) {}

    /**
     * Identitas ini murni milik satu koperasi — tidak ada "identitas
     * super_admin". Tanpa sesi tenant yang jelas (halaman login sebelum
     * login, atau super_admin yang tidak terikat koperasi manapun), kembali
     * ke default aplikasi alih-alih membaca baris koperasi lain secara acak
     * (lihat CurrentTenant::hasTenant()).
     */
    public function nama(): string
    {
        if (! CurrentTenant::hasTenant()) {
            return config('app.name');
        }

        return Pengaturan::get(self::KEY_NAMA) ?? config('app.name');
    }

    public function alamat(): ?string
    {
        return CurrentTenant::hasTenant() ? Pengaturan::get(self::KEY_ALAMAT) : null;
    }

    public function logoPath(): ?string
    {
        return CurrentTenant::hasTenant() ? Pengaturan::get(self::KEY_LOGO_PATH) : null;
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @param  array{nama: string, alamat: ?string, logo: ?UploadedFile}  $data
     *
     * @throws \DomainException Jika tidak ada sesi tenant yang jelas (mis.
     *                          super_admin) — lihat CurrentTenant::hasTenant().
     */
    public function simpan(array $data): void
    {
        if (! CurrentTenant::hasTenant()) {
            throw new \DomainException('Identitas koperasi khusus per koperasi, dikelola oleh admin_primer masing-masing koperasi.');
        }

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
