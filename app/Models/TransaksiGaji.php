<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiGaji extends Model
{
    protected $table = 'transaksi_gaji';

    protected $fillable = [
        'karyawan_id',
        'bulan',
        'tahun',
        'gaji_pokok',
        'gaji_bersih',
    ];

    protected $casts = [
        'gaji_pokok' => 'decimal:2',
        'gaji_bersih' => 'decimal:2',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function details()
    {
        return $this->hasMany(TransaksiGajiDetail::class);
    }
}
