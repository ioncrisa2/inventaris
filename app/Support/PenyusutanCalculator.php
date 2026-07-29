<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Kalkulator penyusutan fiskal aset tetap, mengikuti UU PPh Pasal 11 &
 * PMK 96/PMK.03/2009: masa manfaat & tarif ditentukan oleh golongan
 * (`kategori` pada Barang), bukan nilai bebas yang bisa diubah per aset.
 * Penyusutan dimulai bulan perolehan (prorata bulanan, bukan tahun penuh
 * sejak hari pembelian), tanpa nilai residu (nilai buku fiskal = 0 di akhir
 * masa manfaat).
 */
class PenyusutanCalculator
{
    public static function masaManfaatTahun(string $kategori): int
    {
        return (int) (config('inventaris.masa_manfaat_penyusutan')[$kategori] ?? 0);
    }

    public static function metode(): string
    {
        return config('inventaris.metode_penyusutan_default');
    }

    public static function tahunAkhirMasaManfaat(string $kategori, Carbon $tanggalPerolehan): int
    {
        $masaManfaatBulan = self::masaManfaatTahun($kategori) * 12;

        return $tanggalPerolehan->copy()->startOfMonth()->addMonths($masaManfaatBulan - 1)->year;
    }

    /**
     * Hitung akumulasi & penyusutan satu tahun kalender (1 Januari s.d. 31
     * Desember $tahunLaporan) untuk satu aset.
     *
     * @return array{
     *     metode: string,
     *     masa_manfaat_tahun: int,
     *     bulan_mulai: Carbon,
     *     bulan_selesai: Carbon,
     *     akumulasi_awal_tahun: string,
     *     penyusutan_tahun_ini: string,
     *     akumulasi_akhir_tahun: string,
     *     nilai_buku_akhir_tahun: string,
     * }
     */
    public static function hitungTahunan(string $kategori, string $hargaPerolehan, Carbon $tanggalPerolehan, int $tahunLaporan): array
    {
        $metode = self::metode();

        if ($metode !== 'garis_lurus') {
            throw new \InvalidArgumentException("Metode penyusutan '{$metode}' belum didukung.");
        }

        $masaManfaatTahun = self::masaManfaatTahun($kategori);

        if ($masaManfaatTahun <= 0) {
            throw new \InvalidArgumentException("Golongan '{$kategori}' tidak punya masa manfaat penyusutan yang dikenal.");
        }

        $masaManfaatBulan = $masaManfaatTahun * 12;
        $bulanMulai = $tanggalPerolehan->copy()->startOfMonth();
        $bulanSelesai = $bulanMulai->copy()->addMonths($masaManfaatBulan - 1);
        $hargaPerolehanBulat = PenggajianCalculator::round2($hargaPerolehan);
        $penyusutanPerBulan = bcdiv($hargaPerolehan, (string) $masaManfaatBulan, 10);

        $akumulasiAwalTahun = self::akumulasiSampaiBulan(
            $bulanMulai,
            $bulanSelesai,
            $hargaPerolehanBulat,
            $penyusutanPerBulan,
            $masaManfaatBulan,
            Carbon::create($tahunLaporan - 1, 12, 1),
        );

        $akumulasiAkhirTahun = self::akumulasiSampaiBulan(
            $bulanMulai,
            $bulanSelesai,
            $hargaPerolehanBulat,
            $penyusutanPerBulan,
            $masaManfaatBulan,
            Carbon::create($tahunLaporan, 12, 1),
        );

        return [
            'metode' => $metode,
            'masa_manfaat_tahun' => $masaManfaatTahun,
            'bulan_mulai' => $bulanMulai,
            'bulan_selesai' => $bulanSelesai,
            'akumulasi_awal_tahun' => $akumulasiAwalTahun,
            'penyusutan_tahun_ini' => bcsub($akumulasiAkhirTahun, $akumulasiAwalTahun, 2),
            'akumulasi_akhir_tahun' => $akumulasiAkhirTahun,
            'nilai_buku_akhir_tahun' => bcsub($hargaPerolehanBulat, $akumulasiAkhirTahun, 2),
        ];
    }

    /**
     * Jadwal penyusutan lengkap dari tahun perolehan sampai tahun akhir
     * masa manfaat (inklusif), dipakai untuk rincian di halaman detail
     * barang supaya seluruh riwayat & proyeksi terlihat sekaligus.
     *
     * @return array<int, array> hasil hitungTahunan() per tahun, keyed tahun
     */
    public static function jadwalTahunan(string $kategori, string $hargaPerolehan, Carbon $tanggalPerolehan): array
    {
        $tahunSelesai = self::tahunAkhirMasaManfaat($kategori, $tanggalPerolehan);
        $jadwal = [];

        for ($tahun = $tanggalPerolehan->year; $tahun <= $tahunSelesai; $tahun++) {
            $jadwal[$tahun] = self::hitungTahunan($kategori, $hargaPerolehan, $tanggalPerolehan, $tahun);
        }

        return $jadwal;
    }

    /**
     * Akumulasi penyusutan sejak $bulanMulai sampai termasuk $bulanTarget
     * (di-clip ke $bulanSelesai). Di bulan terakhir masa manfaat, sisa
     * pembulatan sen dibebankan sekaligus supaya akumulasi persis sama
     * dengan harga perolehan (nilai buku akhir = 0, bukan sisa sen kecil).
     */
    private static function akumulasiSampaiBulan(
        Carbon $bulanMulai,
        Carbon $bulanSelesai,
        string $hargaPerolehanBulat,
        string $penyusutanPerBulan,
        int $masaManfaatBulan,
        Carbon $bulanTarget,
    ): string {
        if ($bulanTarget->lt($bulanMulai)) {
            return '0.00';
        }

        $bulanEfektif = $bulanTarget->gt($bulanSelesai) ? $bulanSelesai : $bulanTarget;
        $jumlahBulanBerjalan = $bulanMulai->diffInMonths($bulanEfektif) + 1;

        if ($jumlahBulanBerjalan >= $masaManfaatBulan) {
            return $hargaPerolehanBulat;
        }

        return PenggajianCalculator::round2(bcmul($penyusutanPerBulan, (string) $jumlahBulanBerjalan, 10));
    }
}
