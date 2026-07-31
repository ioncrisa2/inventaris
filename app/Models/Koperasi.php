<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Koperasi extends Model
{
    protected $table = 'koperasi';

    protected $fillable = ['nama', 'expires_at', 'is_active'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function unitKerja()
    {
        return $this->hasMany(UnitKerja::class);
    }

    public function karyawan()
    {
        return $this->hasMany(Karyawan::class);
    }

    public function barang()
    {
        return $this->hasMany(Barang::class);
    }

    public function absensi()
    {
        return $this->hasMany(Absensi::class);
    }

    public function hariLibur()
    {
        return $this->hasMany(HariLibur::class);
    }

    public function komponenGaji()
    {
        return $this->hasMany(KomponenGaji::class);
    }

    public function transaksiGaji()
    {
        return $this->hasMany(TransaksiGaji::class);
    }
}
