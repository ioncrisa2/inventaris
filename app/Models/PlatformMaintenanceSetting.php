<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['enabled', 'message', 'starts_at', 'ends_at', 'enabled_by', 'disabled_by'])]
class PlatformMaintenanceSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
