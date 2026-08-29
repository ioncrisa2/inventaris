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
        'tanggal_awal_snapshot',
        'tanggal_akhir_snapshot',
        'jumlah_hari_snapshot',
        'jumlah_pengali_snapshot',
        'nominal_hasil',
    ];

    protected $casts = [
        'nilai_snapshot' => 'decimal:2',
        'nominal_hasil' => 'decimal:2',
        'tanggal_awal_snapshot' => 'date',
        'tanggal_akhir_snapshot' => 'date',
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
