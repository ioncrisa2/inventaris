<?php

return [
    'dashboard_cache_ttl_seconds' => (int) env('DASHBOARD_CACHE_TTL', 60),

    /**
     * Golongan inventaris (bukan lagi kategori deskriptif bebas) — mengikuti
     * pengelompokan harta berwujud yang lazim dipakai untuk penyusutan aset:
     * Bukan Bangunan (Kelompok 1-4) dan Bangunan (Permanen/Bukan Permanen).
     */
    'kategori' => [
        'Bukan Bangunan - Kelompok 1',
        'Bukan Bangunan - Kelompok 2',
        'Bukan Bangunan - Kelompok 3',
        'Bukan Bangunan - Kelompok 4',
        'Bangunan - Permanen',
        'Bangunan - Bukan Permanen',
    ],
    'kondisi' => [
        'Baru',
        'Sangat Baik',
        'Baik',
        'Cukup Baik',
        'Perlu Perawatan',
        'Rusak Ringan',
        'Rusak Sedang',
        'Rusak Berat',
        'Dalam Perbaikan',
        'Tidak Berfungsi',
        'Hilang',
        'Dihapus',
    ],
    'kondisi_grup' => [
        'layak' => [
            'label' => 'Layak',
            'values' => ['Baru', 'Sangat Baik', 'Baik', 'Cukup Baik'],
        ],
        'perlu-perhatian' => [
            'label' => 'Perlu perhatian',
            'values' => ['Perlu Perawatan', 'Rusak Ringan'],
        ],
        'bermasalah' => [
            'label' => 'Bermasalah',
            'values' => ['Rusak Sedang', 'Rusak Berat', 'Tidak Berfungsi', 'Hilang'],
        ],
        'proses-arsip' => [
            'label' => 'Dalam proses/arsip',
            'values' => ['Dalam Perbaikan', 'Dihapus'],
        ],
        'belum-diperiksa' => [
            'label' => 'Belum diperiksa',
            'values' => [],
        ],
    ],
    'kategori_kode' => [
        'Bukan Bangunan - Kelompok 1' => 'KL1',
        'Bukan Bangunan - Kelompok 2' => 'KL2',
        'Bukan Bangunan - Kelompok 3' => 'KL3',
        'Bukan Bangunan - Kelompok 4' => 'KL4',
        'Bangunan - Permanen' => 'BGP',
        'Bangunan - Bukan Permanen' => 'BGT',
    ],
    'kategori_label_singkat' => [
        'Bukan Bangunan - Kelompok 1' => 'Kel. 1',
        'Bukan Bangunan - Kelompok 2' => 'Kel. 2',
        'Bukan Bangunan - Kelompok 3' => 'Kel. 3',
        'Bukan Bangunan - Kelompok 4' => 'Kel. 4',
        'Bangunan - Permanen' => 'Bangunan P.',
        'Bangunan - Bukan Permanen' => 'Bangunan NP.',
    ],
    /**
     * Jenis dokumen pendukung barang (nota pembelian, kartu garansi, dst.),
     * dipakai oleh repeater upload dokumen di form barang.
     */
    'jenis_dokumen' => [
        'Nota Pembelian',
        'Kartu Garansi',
        'Manual/Buku Petunjuk',
        'Surat Keterangan',
        'Lainnya',
    ],
    'kondisi_warna' => [
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
    ],
];
