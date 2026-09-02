<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    use BelongsToKoperasi;

    public const METODE_PERHITUNGAN = [
        'nominal_tetap' => 'Nominal Tetap',
        'nominal_tidak_tetap' => 'Nominal Tidak Tetap',
        'nominal_tetap_list' => 'Nominal Tetap - List',
        'persentase' => 'Persentase',
        'persentase_pengali' => 'Persentase × Pengali',
        'per_hari' => 'Per Hari Hadir (Periode Gaji)',
        'harian_manual' => 'Harian (Dikali Jumlah Hari)',
    ];

    public const METODE_INPUT_TRANSAKSI = [
        'nominal_tidak_tetap',
    ];

    public const METODE_DAFTAR_TETAP = 'nominal_tetap_list';

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

    public function rincian()
    {
        return $this->hasMany(KomponenGajiRincian::class);
    }
}
