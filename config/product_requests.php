<?php

return [
    'disk' => env('PRODUCT_REQUEST_DISK', 'local'),
    'directory' => 'product-requests',

    'modules' => [
        'dashboard' => 'Dashboard',
        'inventaris' => 'Inventaris',
        'kepegawaian' => 'Kepegawaian',
        'absensi' => 'Absensi',
        'penggajian' => 'Penggajian',
        'laporan' => 'Laporan',
        'pengguna-akses' => 'Pengguna & Hak Akses',
        'pengaturan' => 'Pengaturan',
        'lainnya' => 'Lainnya',
    ],

    'attachments' => [
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt'],
        'mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ],
        'mime_by_extension' => [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
        ],
        'max_file_kilobytes' => 10 * 1024,
        'max_files_per_submission' => 3,
        'max_files_per_request' => 10,
        'max_total_bytes_per_request' => 20 * 1024 * 1024,
        // MVP: lampiran mengikuti umur tiket dan tidak memiliki penghapusan otomatis.
        'retention' => 'request_lifetime',
    ],
];
