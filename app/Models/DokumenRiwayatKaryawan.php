<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenRiwayatKaryawan extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'dokumen_riwayat_karyawan';

    protected $fillable = [
        'riwayat_karyawan_id',
        'nama_asli',
        'path',
        'mime_type',
        'ukuran',
        'checksum_sha256',
    ];

    public function riwayat()
    {
        return $this->belongsTo(RiwayatKaryawan::class, 'riwayat_karyawan_id');
    }
}
