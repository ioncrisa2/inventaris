<?php

namespace App\Support;

use Illuminate\Http\Request;

class PerPage
{
    /**
     * Pilihan jumlah data per halaman yang boleh diminta lewat query string
     * `per_page`, meniru kontrol "Show N entries" ala DataTables.
     */
    public const OPTIONS = [10, 25, 50, 100];

    public const DEFAULT = 10;

    /**
     * Nilai di luar OPTIONS (termasuk yang bukan angka) jatuh kembali ke
     * default, supaya request tidak bisa memaksa halaman memuat jumlah baris
     * yang sangat besar.
     */
    public static function resolve(Request $request, int $default = self::DEFAULT): int
    {
        $value = (int) $request->input('per_page', $default);

        return in_array($value, self::OPTIONS, true) ? $value : $default;
    }
}
