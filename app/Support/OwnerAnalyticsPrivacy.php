<?php

namespace App\Support;

final class OwnerAnalyticsPrivacy
{
    public const MINIMUM_COHORT = 5;

    public const SUPPRESSION_MESSAGE = 'Data tidak cukup untuk ditampilkan.';

    /**
     * Bungkus nilai sensitif agar view tidak perlu mengulang aturan privasi.
     * Jumlah anggota kohort sengaja tidak dikirim ketika nilainya disembunyikan.
     *
     * @return array{nilai: mixed, disembunyikan: bool, pesan: ?string}
     */
    public function sensitiveValue(mixed $value, int $cohort): array
    {
        $suppressed = $cohort < self::MINIMUM_COHORT;

        return [
            'nilai' => $suppressed ? null : $value,
            'disembunyikan' => $suppressed,
            'pesan' => $suppressed ? self::SUPPRESSION_MESSAGE : null,
        ];
    }

    /**
     * Sembunyikan kelompok kecil beserta labelnya. Menggabungkan semua kelompok
     * kecil ke satu placeholder mencegah nama unit/status langka menjadi petunjuk
     * untuk menyimpulkan identitas seseorang.
     *
     * @param  array<string, int>  $distribution
     * @return list<array{label: string, total: ?int, disembunyikan: bool, pesan: ?string}>
     */
    public function suppressDistribution(array $distribution): array
    {
        $visible = [];
        $hasSmallCohort = false;

        foreach ($distribution as $label => $total) {
            if ($total < self::MINIMUM_COHORT) {
                $hasSmallCohort = true;

                continue;
            }

            $visible[] = [
                'label' => (string) $label,
                'total' => $total,
                'disembunyikan' => false,
                'pesan' => null,
            ];
        }

        if ($hasSmallCohort) {
            $visible[] = [
                'label' => 'Kelompok kecil',
                'total' => null,
                'disembunyikan' => true,
                'pesan' => self::SUPPRESSION_MESSAGE,
            ];
        }

        return $visible;
    }

    /** Normalisasi bilangan uang tanpa melewati float. */
    public function money(string|int $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    /**
     * @param  iterable<string|int>  $values
     */
    public function addMoney(iterable $values): string
    {
        $total = '0.00';

        foreach ($values as $value) {
            $total = bcadd($total, (string) $value, 2);
        }

        return $total;
    }

    /** Persentase non-negatif sebagai decimal string, bukan float. */
    public function percentage(string|int $part, string|int $whole, int $scale = 1): string
    {
        if (bccomp((string) $whole, '0', 8) <= 0) {
            return $scale > 0 ? '0.'.str_repeat('0', $scale) : '0';
        }

        $workingScale = $scale + 6;
        $raw = bcmul(
            bcdiv((string) $part, (string) $whole, $workingScale),
            '100',
            $workingScale,
        );

        $roundingIncrement = $scale > 0
            ? '0.'.str_repeat('0', $scale).'5'
            : '0.5';

        return bcadd($raw, $roundingIncrement, $scale);
    }

    /** Pembagian nilai uang yang hanya dipakai setelah aturan cohort lolos. */
    public function averageMoney(string|int $total, int $cohort): string
    {
        if ($cohort <= 0) {
            return '0.00';
        }

        return bcadd(bcdiv((string) $total, (string) $cohort, 4), '0.005', 2);
    }
}
