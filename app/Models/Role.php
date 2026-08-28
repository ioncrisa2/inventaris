<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role TIDAK pakai trait BelongsToKoperasi (tidak ada global scope) —
 * sengaja, karena Spatie's PermissionRegistrar meng-cache seluruh peta
 * permission lewat eager-load relasi Permission::with('roles'). Kalau Role
 * ikut di-scope, cache global itu jadi bergantung pada auth()->user() yang
 * kebetulan aktif saat cache dibangun, dan tercemar untuk SEMUA request
 * lain sampai cache invalidate — ditemukan langsung lewat test yang gagal
 * saat pertama kali dicoba.
 *
 * koperasi_id di tabel roles tetap ada (Fase 1) dan tetap diisi manual di
 * tempat yang butuh (KoperasiService saat provisioning), tapi query yang
 * perlu "role milik koperasiku saja" harus filter eksplisit
 * (App\Support\CurrentTenant::scopeQuery), bukan lewat global scope.
 */
class Role extends SpatieRole
{
    public const SYSTEM_NAMES = ['system_owner', 'super_admin', 'admin_primer'];

    /**
     * Nama role sistem sengaja snake_case di
     * kode/DB, tapi harus tampil ramah-baca di UI. Role custom bikinan
     * super_admin per koperasi (mis. "Staff", "Kepala Gudang") sudah nama
     * manusiawi apa adanya, jadi cukup ditampilkan langsung.
     */
    private const DISPLAY_NAMES = [
        'system_owner' => 'System Owner',
        'super_admin' => 'Super Admin',
        'admin_primer' => 'Admin Primer',
    ];

    public function displayName(): string
    {
        return self::DISPLAY_NAMES[$this->name] ?? $this->name;
    }

    public function isSystem(): bool
    {
        return in_array($this->name, self::SYSTEM_NAMES, true);
    }

    public function isSuperAdminRole(): bool
    {
        return $this->name === 'super_admin' && $this->koperasi_id === null;
    }

    public function isSystemOwnerRole(): bool
    {
        return $this->name === 'system_owner' && $this->koperasi_id === null;
    }

    public function isAdminPrimerRole(): bool
    {
        return $this->name === 'admin_primer' && $this->koperasi_id !== null;
    }

    /**
     * Relasi biasa (bukan lewat trait) — cuma untuk baca "role ini milik
     * koperasi mana", tidak menambah scope apapun.
     */
    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class);
    }
}
