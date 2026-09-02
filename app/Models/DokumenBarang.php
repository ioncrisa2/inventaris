<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFiles;
use Illuminate\Database\Eloquent\Model;

class DokumenBarang extends Model
{
    use HasStoredFiles;

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
