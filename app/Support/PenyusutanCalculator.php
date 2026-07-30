<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Kalkulator penyusutan fiskal aset tetap, mengikuti UU PPh Pasal 11 &
 * PMK 72 Tahun 2023: masa manfaat & tarif ditentukan oleh golongan
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

    public static function metodeUntukKategori(string $kategori): string
    {
        $metode = self::metode();

        if (! in_array($metode, ['garis_lurus', 'saldo_menurun'], true)) {
            throw new \InvalidArgumentException("Metode penyusutan '{$metode}' tidak dikenal.");
        }

        // Bangunan wajib memakai garis lurus. Kebijakan saldo menurun hanya
        // diterapkan pada harta bukan bangunan dan harus dipakai secara taat asas.
        return str_starts_with($kategori, 'Bangunan -') ? 'garis_lurus' : $metode;
    }

    public static function namaMetode(string $metode): string
    {
        return match ($metode) {
            'garis_lurus' => 'Garis Lurus',
            'saldo_menurun' => 'Saldo Menurun',
            default => $metode,
        };
    }

    public static function tarifTahunan(string $kategori, ?string $metode = null): string
    {
        $metode ??= self::metodeUntukKategori($kategori);
        $tarif = config('kategori_penyusutan.tarif')[$metode][$kategori] ?? null;

        if ($tarif === null) {
            throw new \InvalidArgumentException("Tarif metode '{$metode}' untuk golongan '{$kategori}' tidak dikenal.");
        }

        return (string) $tarif;
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
        $masaManfaatTahun = self::masaManfaatTahun($kategori);

        if ($masaManfaatTahun <= 0) {
            throw new \InvalidArgumentException("Golongan '{$kategori}' tidak punya masa manfaat penyusutan yang dikenal.");
        }

        $metode = self::metodeUntukKategori($kategori);
        $tarifTahunan = self::tarifTahunan($kategori, $metode);

        if ($metode === 'saldo_menurun') {
            return self::hitungSaldoMenurun(
                $kategori,
                $hargaPerolehan,
                $tanggalPerolehan,
                $tahunLaporan,
                $masaManfaatTahun,
                $tarifTahunan,
            );
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
     * Saldo menurun diterapkan pada nilai buku fiskal awal tahun. Tahun
     * perolehan diprorata sejak bulan perolehan, kemudian seluruh nilai buku
     * yang tersisa dibebankan pada akhir masa manfaat fiskal.
     */
    private static function hitungSaldoMenurun(
        string $kategori,
        string $hargaPerolehan,
        Carbon $tanggalPerolehan,
        int $tahunLaporan,
        int $masaManfaatTahun,
        string $tarifTahunan,
    ): array {
        $masaManfaatBulan = $masaManfaatTahun * 12;
        $bulanMulai = $tanggalPerolehan->copy()->startOfMonth();
        $bulanSelesai = $bulanMulai->copy()->addMonths($masaManfaatBulan - 1);
        $hargaPerolehanBulat = PenggajianCalculator::round2($hargaPerolehan);

        $hasilDasar = [
            'metode' => 'saldo_menurun',
            'masa_manfaat_tahun' => $masaManfaatTahun,
            'bulan_mulai' => $bulanMulai,
            'bulan_selesai' => $bulanSelesai,
        ];

        if ($tahunLaporan < $bulanMulai->year) {
            return [
                ...$hasilDasar,
                'akumulasi_awal_tahun' => '0.00',
                'penyusutan_tahun_ini' => '0.00',
                'akumulasi_akhir_tahun' => '0.00',
                'nilai_buku_akhir_tahun' => $hargaPerolehanBulat,
            ];
        }

        $nilaiBuku = $hargaPerolehanBulat;
        $akumulasi = '0.00';

        for ($tahun = $bulanMulai->year; $tahun <= min($tahunLaporan, $bulanSelesai->year); $tahun++) {
            $akumulasiAwalTahun = $akumulasi;

            if ($tahun === $bulanSelesai->year) {
                $penyusutanTahunIni = $nilaiBuku;
            } else {
                $jumlahBulan = $tahun === $bulanMulai->year ? 13 - $bulanMulai->month : 12;
                $penyusutanTahunIni = PenggajianCalculator::round2(
                    bcdiv(
                        bcmul(bcmul($nilaiBuku, $tarifTahunan, 10), (string) $jumlahBulan, 10),
                        '12',
                        10,
                    )
                );

                if (bccomp($penyusutanTahunIni, $nilaiBuku, 2) > 0) {
                    $penyusutanTahunIni = $nilaiBuku;
                }
            }

            $nilaiBuku = bcsub($nilaiBuku, $penyusutanTahunIni, 2);
            $akumulasi = bcadd($akumulasi, $penyusutanTahunIni, 2);

            if ($tahun === $tahunLaporan) {
                return [
                    ...$hasilDasar,
                    'akumulasi_awal_tahun' => $akumulasiAwalTahun,
                    'penyusutan_tahun_ini' => $penyusutanTahunIni,
                    'akumulasi_akhir_tahun' => $akumulasi,
                    'nilai_buku_akhir_tahun' => $nilaiBuku,
                ];
            }
        }

        return [
            ...$hasilDasar,
            'akumulasi_awal_tahun' => $hargaPerolehanBulat,
            'penyusutan_tahun_ini' => '0.00',
            'akumulasi_akhir_tahun' => $hargaPerolehanBulat,
            'nilai_buku_akhir_tahun' => '0.00',
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
