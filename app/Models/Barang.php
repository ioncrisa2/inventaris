<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Concerns\HasStoredFiles;
use App\Support\PenyusutanCalculator;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use BelongsToKoperasi, HasStoredFiles;

    protected $table = 'barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori',
        'jenis_barang',
        'unit_kerja_id',
        'lokasi_penempatan',
        'keterangan_lokasi',
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

    /**
     * Nilai buku pada akhir tahun berjalan. Barang yang telah dihapus tetap
     * disimpan untuk kebutuhan riwayat, tetapi tidak lagi memiliki nilai aset.
     */
    public function nilaiBukuTerakhir(?int $tahun = null): string
    {
        if ($this->kondisiTerakhir?->kondisi === 'Dihapus') {
            return '0.00';
        }

        return PenyusutanCalculator::hitungTahunan(
            $this->kategori,
            (string) $this->harga_perolehan,
            $this->tanggal_perolehan,
            $tahun ?? now()->year,
        )['nilai_buku_akhir_tahun'];
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
