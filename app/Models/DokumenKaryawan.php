<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFiles;
use Illuminate\Database\Eloquent\Model;

class DokumenKaryawan extends Model
{
    use HasStoredFiles;

    protected $table = 'dokumen_karyawan';

    protected $fillable = [
        'karyawan_id',
        'jenis_dokumen',
        'nama_asli',
        'path',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
