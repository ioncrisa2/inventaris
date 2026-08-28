<?php

namespace App\Notifications;

use App\Models\PlatformAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformAnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly PlatformAnnouncement $announcement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'platform',
            'priority' => $this->announcement->priority,
            'event' => 'platform_announcement',
            'title' => $this->announcement->title,
            'body' => 'Ada pengumuman baru dari pengelola platform.',
            'url' => route('announcements.show', $this->announcement),
        ];
    }
}
