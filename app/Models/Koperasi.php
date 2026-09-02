<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Koperasi extends Model
{
    use HasStoredFiles;

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

    public function adminPrimerRole(): HasOne
    {
        return $this->hasOne(Role::class)
            ->where('name', 'admin_primer')
            ->where('guard_name', 'web');
    }

    public function adminPrimerUsers(): HasMany
    {
        return $this->hasMany(User::class)
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.name', 'admin_primer')
                ->where('roles.guard_name', 'web'));
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
