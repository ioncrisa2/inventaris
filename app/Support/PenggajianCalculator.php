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
     * - per_kehadiran: nilai adalah nominal Rupiah per hari hadir, dikalikan
     *   $jumlahHadir (jumlah hari berstatus Hadir pada bulan/tahun transaksi).
     *
     * Seluruh operasi pakai bcmath (bukan float) supaya perhitungan uang
     * tidak kena masalah presisi floating-point.
     */
    public static function hitungNominal(string $metodePerhitungan, string $nilai, ?string $gajiPokok = null, ?int $jumlahHadir = null): string
    {
        if ($metodePerhitungan === 'persentase') {
            $dasar = $gajiPokok ?? '0';
            $hasil = bcmul($dasar, bcdiv($nilai, '100', 10), 10);

            return self::round2($hasil);
        }

        if ($metodePerhitungan === 'per_kehadiran') {
            $hasil = bcmul($nilai, (string) ($jumlahHadir ?? 0), 10);

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
