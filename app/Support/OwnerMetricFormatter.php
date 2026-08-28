<?php

namespace App\Support;

final class OwnerMetricFormatter
{
    /** Format decimal string sebagai Rupiah tanpa pernah melewati float. */
    public static function rupiah(string|int $value): string
    {
        $rounded = bcadd((string) $value, bccomp((string) $value, '0', 2) < 0 ? '-0.50' : '0.50', 0);

        return 'Rp '.self::integer($rounded);
    }

    public static function integer(string|int $value): string
    {
        $value = (string) $value;
        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0');
        $digits = $digits === '' ? '0' : $digits;
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $digits) ?? $digits;

        return ($negative && $digits !== '0' ? '-' : '').$grouped;
    }

    public static function bytes(mixed $bytes): string
    {
        if (! is_numeric($bytes) || (float) $bytes < 0) {
            return 'Tidak tersedia';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1, ',', '.').' '.$units[$unit];
    }
}
