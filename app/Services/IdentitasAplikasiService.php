<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\Pengaturan;
use App\Models\StoredFile;
use App\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdentitasAplikasiService
{
    private const KEY_NAMA = 'identitas_nama';

    private const KEY_ALAMAT = 'identitas_alamat';

    private const KEY_LOGO_PATH = 'identitas_logo_path';

    public function __construct(
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
    ) {}

    /**
     * Nama aplikasi bersifat dinamis:
     * - Ketika super_admin login atau pada halaman publik (tanpa tenant), menggunakan default "Puskopdit PHS" (config('app.name')).
     * - Ketika admin primer / staf koperasi login, otomatis menampilkan nama koperasi miliknya ($user->koperasi->nama), kecuali jika dikustomisasi di pengaturan.
     */
    public function nama(): string
    {
        if (! CurrentTenant::hasTenant()) {
            return config('app.name');
        }

        $customNama = Pengaturan::get(self::KEY_NAMA);
        if (filled($customNama)) {
            return $customNama;
        }

        $koperasiNama = Auth::user()?->koperasi?->nama;
        if (filled($koperasiNama)) {
            return $koperasiNama;
        }

        return config('app.name');
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

        $prepared = ($data['logo'] ?? null) instanceof UploadedFile
            ? $this->storedFiles->prepare($data['logo'], 'logo')
            : null;
        $logoToken = $data['logo_upload_uuid'] ?? null;

        try {
            DB::transaction(function () use ($data, $prepared, $logoToken) {
                $logoLama = null;

                if ($prepared || $logoToken) {
                    $koperasiId = CurrentTenant::id();
                    $koperasi = Koperasi::query()->findOrFail($koperasiId);
                    $logoLama = $this->logoPath();
                    $this->storedFiles->deleteForOwner($koperasi, 'logo', 'replace');
                }
                if ($prepared) {
                    $registry = $this->storedFiles->persist(
                        $prepared,
                        $koperasiId,
                        'logo',
                        $koperasi,
                        auth()->id(),
                    );
                    Pengaturan::set(self::KEY_LOGO_PATH, $registry->path);
                } elseif ($logoToken) {
                    $token = StoredFile::query()->where('uuid', $logoToken)->firstOrFail();
                    $claimed = $this->asyncUploads->claim($token, $koperasi, auth()->user(), 'logo', 'logo');
                    Pengaturan::set(self::KEY_LOGO_PATH, $claimed->isAvailable() ? $claimed->path : '');
                }

                Pengaturan::set(self::KEY_NAMA, $data['nama']);
                Pengaturan::set(self::KEY_ALAMAT, (string) ($data['alamat'] ?? ''));

                $this->fileStorage->deleteAfterCommit('public', $logoLama);
            }, 3);
        } finally {
            $prepared?->cleanup();
        }
    }
}
