<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    public const METODE_PERHITUNGAN = [
        'nominal_tetap' => 'Nominal Tetap',
        'persentase' => 'Persentase',
        'per_hari' => 'Per Hari (Range Tanggal)',
    ];

    protected $table = 'komponen_gaji';

    protected $fillable = [
        'nama_komponen',
        'jenis',
        'metode_perhitungan',
        'nilai_default',
        'dasar_persentase',
    ];

    protected $casts = [
        'nilai_default' => 'decimal:2',
    ];

    public function transaksiGajiDetails()
    {
        return $this->hasMany(TransaksiGajiDetail::class);
    }
}
