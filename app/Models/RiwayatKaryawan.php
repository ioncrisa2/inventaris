<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFiles;
use Illuminate\Database\Eloquent\Model;

class RiwayatKaryawan extends Model
{
    use HasStoredFiles;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_karyawan';

    protected $fillable = [
        'karyawan_id',
        'user_id',
        'nama_pelaku_snapshot',
        'jenis_perubahan',
        'tanggal_berlaku',
        'alasan',
        'sumber',
    ];

    protected $casts = [
        'tanggal_berlaku' => 'date',
        'created_at' => 'datetime',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function pelaku()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function perubahan()
    {
        return $this->hasMany(RiwayatKaryawanPerubahan::class);
    }

    public function dokumen()
    {
        return $this->hasMany(DokumenRiwayatKaryawan::class);
    }
}
