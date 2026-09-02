<?php

return [
    'enabled' => (bool) env('LOCAL_BACKUP_ENABLED', false),
    'repository' => env('RESTIC_REPOSITORY', '/backups/restic'),
    'password' => env('RESTIC_PASSWORD'),
    'password_file' => env('RESTIC_PASSWORD_FILE'),
    'storage_path' => env('BACKUP_STORAGE_PATH', storage_path('app')),
    'metadata_path' => env('OWNER_BACKUP_METADATA_PATH', storage_path('app/health/backup.json')),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 3600),
    'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 7),
    'keep_weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
];
