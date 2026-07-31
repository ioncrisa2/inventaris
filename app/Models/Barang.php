<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use BelongsToKoperasi;

    protected $table = 'barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori',
        'jenis_barang',
        'unit_kerja_id',
        'tanggal_perolehan',
        'harga_perolehan',
        'foto_sampul',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'harga_perolehan' => 'decimal:2',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function riwayatKondisi()
    {
        return $this->hasMany(RiwayatKondisiBarang::class);
    }

    public function kondisiTerakhir()
    {
        return $this->hasOne(RiwayatKondisiBarang::class)->latestOfMany('tanggal_pemeriksaan');
    }

    public function fotoPendukung()
    {
        return $this->hasMany(FotoBarang::class);
    }

    public function dokumen()
    {
        return $this->hasMany(DokumenBarang::class);
    }
}
