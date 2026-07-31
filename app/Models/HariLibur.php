<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use BelongsToKoperasi;

    protected $table = 'hari_libur';

    protected $fillable = ['tanggal', 'keterangan'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];
}
