<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['feature_key', 'enabled', 'updated_by'])]
class PlatformFeatureSetting extends Model
{
    protected $primaryKey = 'feature_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
