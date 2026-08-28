<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_user_id', 'transaksi_gaji_id', 'action'])]
class SalaryAccessLog extends Model
{
    public const UPDATED_AT = null;
}
