<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatKaryawanPerubahan extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_karyawan_perubahan';

    protected $fillable = [
        'riwayat_karyawan_id',
        'field',
        'label',
        'nilai_lama',
        'nilai_baru',
        'tampilan_lama',
        'tampilan_baru',
        'sensitif',
    ];

    protected $casts = [
        'sensitif' => 'boolean',
    ];

    public function riwayat()
    {
        return $this->belongsTo(RiwayatKaryawan::class, 'riwayat_karyawan_id');
    }
}
