<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['feature_key', 'actor_user_id', 'action'])]
class PlatformFeatureAuditLog extends Model
{
    public const UPDATED_AT = null;
}
