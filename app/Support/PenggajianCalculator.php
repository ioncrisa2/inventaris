<?php

namespace App\Support;

class PenggajianCalculator
{
    /**
     * Hitung nominal hasil satu komponen gaji.
     *
     * - nominal_tetap: nilai adalah nominal Rupiah, dipakai apa adanya.
     * - persentase: nilai adalah angka persentase (mis. 5 = 5%), dihitung
     *   dari $gajiPokok.
     * - per_hari: nilai adalah nominal Rupiah per hari, dikalikan
     *   $jumlahHari (jumlah hari operasional pada rentang tanggal yang
     *   diinput manual saat transaksi dibuat — hari libur sesuai setting
     *   Hari Operasional tidak dihitung, lihat HariOperasionalService).
     *
     * Seluruh operasi pakai bcmath (bukan float) supaya perhitungan uang
     * tidak kena masalah presisi floating-point.
     */
    public static function hitungNominal(string $metodePerhitungan, string $nilai, ?string $gajiPokok = null, ?int $jumlahHari = null): string
    {
        if ($metodePerhitungan === 'persentase') {
            $dasar = $gajiPokok ?? '0';
            $hasil = bcmul($dasar, bcdiv($nilai, '100', 10), 10);

            return self::round2($hasil);
        }

        if ($metodePerhitungan === 'per_hari') {
            $hasil = bcmul($nilai, (string) ($jumlahHari ?? 0), 10);

            return self::round2($hasil);
        }

        return self::round2($nilai);
    }

    /**
     * Bulatkan nilai desimal (string, non-negatif) ke 2 angka di belakang
     * koma dengan aturan round half up. bcadd()/bcdiv() dengan scale hanya
     * memotong (truncate), jadi pembulatan dilakukan manual di sini.
     */
    public static function round2(string $value): string
    {
        $sen = bcadd(bcmul($value, '100', 10), '0.5', 0);

        return bcdiv($sen, '100', 2);
    }
}
