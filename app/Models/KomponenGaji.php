<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    use BelongsToKoperasi;

    public const METODE_PERHITUNGAN = [
        'nominal_tetap' => 'Nominal Tetap',
        'persentase' => 'Persentase',
        'persentase_pengali' => 'Persentase × Pengali',
        'per_hari' => 'Per Hari (Range Tanggal)',
        'harian_sehari' => 'Harian (Sehari)',
        'harian_manual' => 'Harian (Dikali Jumlah Hari)',
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
