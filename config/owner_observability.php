<?php

return [
    /*
    | Cache observability sengaja terpisah dari cache dashboard tenant.
    | Nilai singkat menjaga halaman tetap responsif tanpa menyamarkan gangguan
    | terlalu lama.
    */
    'health_cache_seconds' => (int) env('OWNER_HEALTH_CACHE_SECONDS', 45),
    'storage_cache_seconds' => (int) env('OWNER_STORAGE_CACHE_SECONDS', 600),
    'max_check_duration_ms' => (int) env('OWNER_HEALTH_MAX_CHECK_DURATION_MS', 2000),

    'database' => [
        'warning_latency_ms' => (float) env('OWNER_DB_WARNING_LATENCY_MS', 250),
        'critical_latency_ms' => (float) env('OWNER_DB_CRITICAL_LATENCY_MS', 1000),
    ],

    'queue' => [
        'warning_pending_jobs' => (int) env('OWNER_QUEUE_WARNING_PENDING', 100),
        'critical_pending_jobs' => (int) env('OWNER_QUEUE_CRITICAL_PENDING', 1000),
        'warning_failed_jobs' => (int) env('OWNER_QUEUE_WARNING_FAILED', 1),
        'critical_failed_jobs' => (int) env('OWNER_QUEUE_CRITICAL_FAILED', 10),
    ],

    'media' => [
        'warning_pending_age_seconds' => (int) env('OWNER_MEDIA_WARNING_PENDING_AGE', 3600),
        'critical_pending_age_seconds' => (int) env('OWNER_MEDIA_CRITICAL_PENDING_AGE', 86400),
        'warning_staging_backlog' => (int) env('OWNER_MEDIA_WARNING_STAGING', 100),
        'critical_staging_backlog' => (int) env('OWNER_MEDIA_CRITICAL_STAGING', 500),
        'max_reference_checks' => (int) env('OWNER_MEDIA_MAX_REFERENCE_CHECKS', 1000),
    ],

    'scheduler' => [
        'cache_key' => 'owner-observability:v1:scheduler-heartbeat',
        'heartbeat_ttl_seconds' => (int) env('OWNER_SCHEDULER_HEARTBEAT_TTL_SECONDS', 172800),
        'warning_after_seconds' => (int) env('OWNER_SCHEDULER_WARNING_AFTER_SECONDS', 150),
        'critical_after_seconds' => (int) env('OWNER_SCHEDULER_CRITICAL_AFTER_SECONDS', 300),
    ],

    /*
    | File ini harus dihasilkan proses backup, bukan disimpulkan dari adanya
    | sebuah folder. Path tidak pernah dikirim ke browser atau log aplikasi.
    |
    | Bentuk metadata yang didukung:
    | {"status":"success","completed_at":"...","size_bytes":123,
    |  "total_size_bytes":456,"files_count":2}
    */
    'backup' => [
        'metadata_path' => env('OWNER_BACKUP_METADATA_PATH', storage_path('app/health/backup.json')),
        'max_metadata_bytes' => 65536,
        'warning_after_hours' => (int) env('OWNER_BACKUP_WARNING_AFTER_HOURS', 26),
        'critical_after_hours' => (int) env('OWNER_BACKUP_CRITICAL_AFTER_HOURS', 72),
        'warning_check_after_days' => (int) env('OWNER_BACKUP_WARNING_CHECK_AFTER_DAYS', 8),
        'warning_restore_after_days' => (int) env('OWNER_BACKUP_WARNING_RESTORE_AFTER_DAYS', 8),
    ],

    'deployment' => [
        'release' => env('APP_RELEASE'),
        'deployed_at' => env('APP_DEPLOYED_AT'),
    ],

    /*
    | Counter berikut bersifat opt-in. Exception/log mentah sengaja tidak
    | dipindai oleh health service karena dapat membawa data tenant atau secret.
    */
    'metrics' => [
        'error_count_cache_key' => env('OWNER_ERROR_COUNT_CACHE_KEY'),
        'mail_last_error_cache_key' => env('OWNER_MAIL_LAST_ERROR_CACHE_KEY'),
    ],

    'storage' => [
        'capacity_disk' => env('OWNER_CAPACITY_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_references_per_category' => (int) env('OWNER_STORAGE_MAX_REFERENCES', 50000),
        'warning_free_percent' => (float) env('OWNER_STORAGE_WARNING_FREE_PERCENT', 25),
        'critical_free_percent' => (float) env('OWNER_STORAGE_CRITICAL_FREE_PERCENT', 5),
    ],
];
