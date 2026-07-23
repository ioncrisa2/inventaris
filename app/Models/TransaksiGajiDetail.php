<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiGajiDetail extends Model
{
    protected $table = 'transaksi_gaji_detail';

    protected $fillable = [
        'transaksi_gaji_id',
        'komponen_gaji_id',
        'nama_komponen_snapshot',
        'jenis_snapshot',
        'metode_perhitungan_snapshot',
        'nilai_snapshot',
        'dasar_persentase_snapshot',
        'jumlah_hadir_snapshot',
        'nominal_hasil',
    ];

    protected $casts = [
        'nilai_snapshot' => 'decimal:2',
        'nominal_hasil' => 'decimal:2',
    ];

    public function transaksiGaji()
    {
        return $this->belongsTo(TransaksiGaji::class);
    }

    public function komponenGaji()
    {
        return $this->belongsTo(KomponenGaji::class);
    }
}
