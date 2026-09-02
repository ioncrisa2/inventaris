<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StoredFileRejected extends Notification
{
    use Queueable;

    public function __construct(private string $uuid) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Upload ditolak',
            'message' => 'Sebuah file ditolak karena terdeteksi berbahaya. Silakan gunakan file lain.',
            'upload_uuid' => $this->uuid,
        ];
    }
}
