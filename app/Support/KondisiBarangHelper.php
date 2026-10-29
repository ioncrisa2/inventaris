<?php

namespace App\Support;

class KondisiBarangHelper
{
    /**
     * Kondisi yang masuk dalam kategori "Perlu Perbaikan" pada kartu dan ringkasan.
     */
    public const KONDISI_PERLU_PERBAIKAN = ['Rusak Ringan', 'Rusak Berat'];

    /**
     * @return array<string, array{label: string, values: list<string>}>
     */
    public static function grup(): array
    {
        return config('inventaris.kondisi_grup', []);
    }

    /**
     * Tentukan nama grup ('layak', 'perlu-perhatian', 'bermasalah', 'proses-arsip', 'belum-diperiksa')
     * berdasarkan string kondisi terakhir barang.
     */
    public static function grupUntukKondisi(?string $kondisi): string
    {
        if (blank($kondisi)) {
            return 'belum-diperiksa';
        }

        foreach (self::grup() as $key => $config) {
            if (in_array($kondisi, $config['values'] ?? [], true)) {
                return $key;
            }
        }

        return 'belum-diperiksa';
    }

    /**
     * Label grup kondisi (misal 'Layak', 'Perlu perhatian', 'Bermasalah').
     */
    public static function labelGrup(string $grupKey): string
    {
        return self::grup()[$grupKey]['label'] ?? ucfirst(str_replace('-', ' ', $grupKey));
    }

    /**
     * Cek apakah kondisi termasuk perlu perbaikan.
     */
    public static function isPerluPerbaikan(?string $kondisi): bool
    {
        return in_array($kondisi, self::KONDISI_PERLU_PERBAIKAN, true);
    }

    /**
     * Class CSS / warna badge untuk tiap kondisi, teruji memenuhi kontras teks minimal 4.5:1.
     */
    public static function badgeClass(?string $kondisi): string
    {
        return match ($kondisi) {
            'Baru' => 'condition-badge--new',
            'Sangat Baik' => 'condition-badge--excellent',
            'Baik' => 'condition-badge--good',
            'Cukup Baik' => 'condition-badge--fair',
            'Perlu Perawatan' => 'condition-badge--maintenance',
            'Rusak Ringan' => 'condition-badge--minor',
            'Rusak Sedang' => 'condition-badge--moderate',
            'Rusak Berat' => 'condition-badge--severe',
            'Dalam Perbaikan' => 'condition-badge--repair',
            'Tidak Berfungsi' => 'condition-badge--broken',
            'Hilang' => 'condition-badge--lost',
            'Dihapus' => 'condition-badge--archived',
            default => 'bg-secondary text-white',
        };
    }
}

