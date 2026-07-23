<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenBarang extends Model
{
    protected $table = 'dokumen_barang';

    protected $fillable = [
        'barang_id',
        'jenis_dokumen',
        'nama_asli',
        'path',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
