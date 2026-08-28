<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class TransaksiGaji extends Model
{
    use BelongsToKoperasi;

    protected $table = 'transaksi_gaji';

    protected $fillable = [
        'karyawan_id',
        'bulan',
        'tahun',
        'gaji_pokok',
        'gaji_bersih',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'gaji_pokok' => 'decimal:2',
        'gaji_bersih' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function details()
    {
        return $this->hasMany(TransaksiGajiDetail::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
