<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id',
    'koperasi_id',
    'action',
    'route',
    'response_status',
    'ip_address',
    'user_agent',
    'filters',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }
}
