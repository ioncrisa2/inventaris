<?php

namespace App\Services;

use App\Models\Barang;
use App\Repositories\BarangRepository;
use App\Repositories\RiwayatKondisiBarangRepository;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarangService
{
    public function __construct(
        private BarangRepository $barangRepository,
        private RiwayatKondisiBarangRepository $riwayatKondisiBarangRepository,
        private KodeBarangGenerator $kodeBarangGenerator,
        private FotoBarangService $fotoBarangService,
        private DokumenBarangService $dokumenBarangService,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    /**
     * @param  array{search?: ?string, unit_kerja_id?: ?string, kategori?: ?string, kondisi?: ?string, kelengkapan?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->barangRepository->paginate($filters, $perPage);
    }

    /**
     * Simpan barang baru sekaligus catatan kondisi awalnya (satu transaction),
     * dengan kondisi awal ditentukan otomatis dari besar harga perolehannya.
     */
    public function store(array $data): Barang
    {
        return DB::transaction(function () use ($data) {
            $kondisiAwal = $this->kondisiAwal((float) $data['harga_perolehan']);
            $data['kode_barang'] = $this->kodeBarangGenerator->generate(
                $data['kategori'],
                (int) $data['unit_kerja_id'],
                $data['tanggal_perolehan'],
            );
            $data = $this->simpanFotoSampul($data);
            $fotoPendukung = array_filter($data['foto_pendukung'] ?? [], fn ($foto) => $foto instanceof UploadedFile);
            unset($data['foto_pendukung']);
            $dokumenBaris = $this->dokumenTerisi($data);
            unset($data['dokumen']);

            $barang = $this->barangRepository->create($data);

            $this->riwayatKondisiBarangRepository->create($barang, [
                'tanggal_pemeriksaan' => now()->toDateString(),
                'kondisi' => $kondisiAwal,
                'keterangan' => "Kondisi awal saat barang ditambahkan (otomatis: {$kondisiAwal}).",
                'biaya_perbaikan' => null,
            ]);

            foreach ($fotoPendukung as $foto) {
                $this->fotoBarangService->store($barang, ['foto' => $foto]);
            }

            $this->simpanDokumen($barang, $dokumenBaris);
            $this->dashboardCache->invalidateAfterCommit();

            return $barang;
        }, 3);
    }

    public function update(Barang $barang, array $data): Barang
    {
        return DB::transaction(function () use ($barang, $data) {
            $fotoLama = null;

            if (isset($data['foto_sampul']) && $data['foto_sampul'] instanceof UploadedFile) {
                $fotoLama = $barang->foto_sampul;
                $data = $this->simpanFotoSampul($data);
            } else {
                // Tidak ada file baru diupload: jangan timpa foto_sampul yang sudah tersimpan.
                unset($data['foto_sampul']);
            }

            $dokumenBaris = $this->dokumenTerisi($data);
            unset($data['dokumen']);
            $barang = $this->barangRepository->update($barang, $data);

            $this->simpanDokumen($barang, $dokumenBaris);
            $this->fileStorage->deleteAfterCommit('public', $fotoLama);
            $this->dashboardCache->invalidateAfterCommit();

            return $barang;
        }, 3);
    }

    public function destroy(Barang $barang): void
    {
        $this->destroyMany([$barang->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $barangs = $this->barangRepository->findManyForDelete($ids);
            $this->pastikanSemuaDitemukan($barangs->count(), count($ids));
            $barangs->each(fn (Barang $barang) => $this->ensureCanDelete($barang));

            foreach ($barangs as $barang) {
                $fotoSampul = $barang->foto_sampul;
                $this->barangRepository->delete($barang);
                $this->fileStorage->deleteAfterCommit('public', $fotoSampul);
            }

            $this->dashboardCache->invalidateAfterCommit();

            return $barangs->count();
        }, 3);
    }

    private function pastikanSemuaDitemukan(int $jumlahDitemukan, int $jumlahDiminta): void
    {
        if ($jumlahDiminta === 0 || $jumlahDitemukan !== $jumlahDiminta) {
            throw new \DomainException('Sebagian barang sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
        }
    }

    public function ensureCanDelete(Barang $barang): void
    {
        $atribut = $barang->getAttributes();
        $memilikiRelasi = array_key_exists('riwayat_kondisi_exists', $atribut)
            ? (bool) ($barang->riwayat_kondisi_exists || $barang->foto_pendukung_exists || $barang->dokumen_exists)
            : ($barang->riwayatKondisi()->exists() || $barang->fotoPendukung()->exists() || $barang->dokumen()->exists());

        if ($memilikiRelasi) {
            throw new \DomainException('Barang tidak dapat dihapus karena masih memiliki riwayat kondisi, foto pendukung, atau dokumen. Hapus relasi tersebut terlebih dahulu.');
        }
    }

    /**
     * QR code yang meng-encode link ke halaman detail barang, di-generate
     * on-the-fly (tidak disimpan sebagai file) supaya selalu sinkron kalau
     * URL berubah.
     */
    public function qrCodeSvg(Barang $barang): string
    {
        $builder = new Builder(writer: new SvgWriter);

        return $builder->build(data: route('barang.show', $barang))->getString();
    }

    /**
     * Barcode Code128 klasik yang meng-encode kode_barang itu sendiri, untuk
     * alur kerja scanner-gun.
     */
    public function barcodeCode128Svg(Barang $barang): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($barang->kode_barang, $generator::TYPE_CODE_128);
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

    private function simpanDokumen(Barang $barang, array $dokumenBaris): void
    {
        foreach ($dokumenBaris as $baris) {
            $this->dokumenBarangService->store($barang, [
                'jenis_dokumen' => $baris['jenis_dokumen'],
                'dokumen' => $baris['dokumen'],
            ]);
        }
    }

    private function simpanFotoSampul(array $data): array
    {
        if (isset($data['foto_sampul']) && $data['foto_sampul'] instanceof UploadedFile) {
            $data['foto_sampul'] = $this->fileStorage->store('public', 'barang-sampul', $data['foto_sampul']);
        } else {
            unset($data['foto_sampul']);
        }

        return $data;
    }

    /**
     * Contoh variabel, tipe data, dan percabangan if-elseif-else: kondisi awal
     * barang ditentukan otomatis dari besar harga perolehannya.
     */
    private function kondisiAwal(float $hargaPerolehan): string
    {
        $batasHargaBaru = 10000000; // Rp 10 juta (tipe data int)

        if ($hargaPerolehan >= $batasHargaBaru) {
            return 'Baru';
        }

        if ($hargaPerolehan > 0) {
            return 'Baik';
        }

        return 'Cukup Baik';
    }
}
