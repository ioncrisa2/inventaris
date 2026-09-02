<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenGajiRincian extends Model
{
    protected $table = 'komponen_gaji_rincian';

    protected $fillable = [
        'keterangan',
        'nominal',
        'urutan',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'urutan' => 'integer',
    ];

    public function komponenGaji()
    {
        return $this->belongsTo(KomponenGaji::class);
    }
}
