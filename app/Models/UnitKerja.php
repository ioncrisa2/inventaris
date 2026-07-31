<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    use BelongsToKoperasi;

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
