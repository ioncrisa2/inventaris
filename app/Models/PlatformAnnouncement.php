<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'created_by',
    'target_koperasi_id',
    'title',
    'body',
    'priority',
    'starts_at',
    'ends_at',
    'published_at',
])]
class PlatformAnnouncement extends Model
{
    public const PRIORITIES = ['info', 'warning', 'critical'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isVisibleNow(): bool
    {
        $now = now();

        return $this->published_at !== null
            && (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gt($now));
    }
}
