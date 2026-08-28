<?php

namespace App\Services;

use App\Models\PlatformAnnouncement;
use App\Models\User;
use App\Notifications\PlatformAnnouncementPublished;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformAnnouncementService
{
    public function create(User $actor, array $data): PlatformAnnouncement
    {
        return PlatformAnnouncement::query()->create([
            ...$data,
            'created_by' => $actor->id,
        ]);
    }

    public function publish(PlatformAnnouncement $announcement): PlatformAnnouncement
    {
        return DB::transaction(function () use ($announcement) {
            $locked = PlatformAnnouncement::query()->whereKey($announcement->id)->lockForUpdate()->firstOrFail();

            if ($locked->published_at !== null) {
                throw new \DomainException('Pengumuman sudah diterbitkan.');
            }

            $locked->published_at = now();
            $locked->save();

            DB::afterCommit(function () use ($locked) {
                $this->recipientQuery($locked)->eachById(
                    fn (User $recipient) => $recipient->notify(new PlatformAnnouncementPublished($locked)),
                );
            });

            return $locked;
        }, 3);
    }

    private function recipientQuery(PlatformAnnouncement $announcement): Builder
    {
        return User::query()
            ->whereNotNull('koperasi_id')
            ->when(
                $announcement->target_koperasi_id !== null,
                fn (Builder $query) => $query->where('koperasi_id', $announcement->target_koperasi_id),
            )
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('roles.name', 'admin_primer')
                ->whereColumn('roles.koperasi_id', 'users.koperasi_id'));
    }
}
