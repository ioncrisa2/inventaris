<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_user_id', 'karyawan_id', 'target_user_id', 'action'])]
class KaryawanAccountAuditLog extends Model
{
    public const UPDATED_AT = null;
}
