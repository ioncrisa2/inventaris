<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoredFileAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'stored_file_id', 'file_uuid', 'koperasi_id', 'actor_id', 'event', 'ip_hash', 'context',
    ];
}
