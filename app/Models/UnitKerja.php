<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    protected $table = 'unit_kerja';

    protected $fillable = ['nama_unit', 'kode'];

    public function karyawan()
    {
        return $this->hasMany(Karyawan::class);
    }

    public function barang()
    {
        return $this->hasMany(Barang::class);
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }
}
