<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatKondisiBarang extends Model
{
    protected $table = 'riwayat_kondisi_barang';

    protected $fillable = [
        'barang_id',
        'tanggal_pemeriksaan',
        'kondisi',
        'keterangan',
        'biaya_perbaikan',
    ];

    protected $casts = [
        'tanggal_pemeriksaan' => 'date',
        'biaya_perbaikan' => 'decimal:2',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
