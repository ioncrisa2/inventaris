<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Repositories\KaryawanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class KaryawanService
{
    public function __construct(
        private KaryawanRepository $karyawanRepository,
        private DokumenKaryawanService $dokumenKaryawanService,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    /**
     * @param  array{search?: ?string, unit_kerja_id?: ?string, status_karyawan?: ?string, kelengkapan?: ?string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->karyawanRepository->paginate($filters);
    }

    /**
     * Simpan karyawan baru sekaligus dokumen pendukung dari repeater (baris
     * kosong yang tidak diisi file diabaikan), dalam satu transaction.
     */
    public function store(array $data): Karyawan
    {
        return DB::transaction(function () use ($data) {
            $dokumenBaris = $this->dokumenTerisi($data);
            unset($data['dokumen']);
            $data = $this->simpanFotoKaryawan($data);
            $karyawan = $this->karyawanRepository->create($data);

            $this->simpanDokumen($karyawan, $dokumenBaris);
            $this->dashboardCache->invalidateAfterCommit();

            return $karyawan;
        });
    }

    public function update(Karyawan $karyawan, array $data): Karyawan
    {
        return DB::transaction(function () use ($karyawan, $data) {
            $dokumenBaris = $this->dokumenTerisi($data);
            unset($data['dokumen']);
            $fotoLama = null;

            if (isset($data['foto_karyawan']) && $data['foto_karyawan'] instanceof UploadedFile) {
                $fotoLama = $karyawan->foto_karyawan;
                $data = $this->simpanFotoKaryawan($data);
            } else {
                // Tidak ada file baru diupload: jangan timpa foto_karyawan yang sudah tersimpan.
                unset($data['foto_karyawan']);
            }

            $karyawan = $this->karyawanRepository->update($karyawan, $data);

            $this->simpanDokumen($karyawan, $dokumenBaris);
            $this->fileStorage->deleteAfterCommit('public', $fotoLama);
            $this->dashboardCache->invalidateAfterCommit();

            return $karyawan;
        });
    }

    private function simpanFotoKaryawan(array $data): array
    {
        if (isset($data['foto_karyawan']) && $data['foto_karyawan'] instanceof UploadedFile) {
            $data['foto_karyawan'] = $this->fileStorage->store('public', 'karyawan-foto', $data['foto_karyawan']);
        } else {
            unset($data['foto_karyawan']);
        }

        return $data;
    }

    /**
     * Baris repeater dokumen yang benar-benar diisi file (baris kosong yang
     * tersisa karena tombol tambah/hapus tidak dipakai, diabaikan saja).
     */
    private function dokumenTerisi(array $data): array
    {
        return array_filter(
            $data['dokumen'] ?? [],
            fn ($baris) => isset($baris['dokumen']) && $baris['dokumen'] instanceof UploadedFile
        );
    }

    private function simpanDokumen(Karyawan $karyawan, array $dokumenBaris): void
    {
        foreach ($dokumenBaris as $baris) {
            $this->dokumenKaryawanService->store($karyawan, [
                'jenis_dokumen' => $baris['jenis_dokumen'],
                'dokumen' => $baris['dokumen'],
            ]);
        }
    }

    public function destroy(Karyawan $karyawan): void
    {
        $this->destroyMany([$karyawan->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $karyawans = $this->karyawanRepository->findManyForDelete($ids);

            if ($ids === [] || $karyawans->count() !== count($ids)) {
                throw new \DomainException('Sebagian karyawan sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $karyawans->each(fn (Karyawan $karyawan) => $this->ensureCanDelete($karyawan));

            foreach ($karyawans as $karyawan) {
                $foto = $karyawan->foto_karyawan;
                $dokumen = $karyawan->dokumen->pluck('path')->all();

                $this->karyawanRepository->delete($karyawan);

                $this->fileStorage->deleteAfterCommit('public', $foto);
                foreach ($dokumen as $path) {
                    $this->fileStorage->deleteAfterCommit('local', $path);
                }
            }

            $this->dashboardCache->invalidateAfterCommit();

            return $karyawans->count();
        }, 3);
    }

    public function ensureCanDelete(Karyawan $karyawan): void
    {
        $atribut = $karyawan->getAttributes();
        $memilikiRelasi = array_key_exists('absensis_exists', $atribut)
            ? (bool) ($karyawan->absensis_exists || $karyawan->transaksi_gaji_exists || $karyawan->bawahan_langsung_exists)
            : ($karyawan->absensis()->exists() || $karyawan->transaksiGaji()->exists() || $karyawan->bawahanLangsung()->exists());

        if ($memilikiRelasi) {
            throw new \DomainException('Karyawan tidak dapat dihapus karena masih terhubung dengan absensi, transaksi gaji, atau tercatat sebagai atasan. Hapus atau pindahkan relasi tersebut terlebih dahulu.');
        }
    }

    /**
     * Kategorikan usia karyawan untuk ditampilkan di halaman detail.
     */
    public function kategoriUsia(Karyawan $karyawan): string
    {
        $usia = $karyawan->tanggal_lahir->age;

        if ($usia < 25) {
            return 'Usia Muda (< 25 tahun)';
        }

        if ($usia >= 25 && $usia <= 50) {
            return 'Usia Produktif (25-50 tahun)';
        }

        return 'Mendekati Pensiun (> 50 tahun)';
    }

    /**
     * Masa kerja karyawan dari tanggal masuk s.d. tanggal mengundurkan diri
     * (kalau sudah keluar) atau hari ini (kalau masih aktif).
     */
    public function masaKerja(Karyawan $karyawan): string
    {
        if (! $karyawan->tanggal_masuk_kerja) {
            return 'Belum diketahui';
        }

        $mulai = $karyawan->tanggal_masuk_kerja;
        $akhir = $karyawan->tanggal_mengundurkan_diri ?? now();

        $tahun = (int) $mulai->diffInYears($akhir);
        $bulan = (int) $mulai->copy()->addYears($tahun)->diffInMonths($akhir);

        return "{$tahun} tahun {$bulan} bulan";
    }
}
