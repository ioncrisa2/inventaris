<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

class CurrentTenant
{
    /**
     * Terapkan filter koperasi_id ke query builder — dipakai di query yang
     * TIDAK otomatis kena global scope KoperasiScope: query mentah
     * (DB::table(), tidak lewat Eloquent sama sekali), atau model yang
     * sengaja tidak pakai trait BelongsToKoperasi (mis. Role — lihat
     * catatan di App\Models\Role kenapa Role tidak boleh pakai global
     * scope). Perilakunya sengaja dibuat identik dengan KoperasiScope:
     * tidak difilter kalau tidak ada user login atau usernya super_admin,
     * kalau tidak filter ke koperasi_id user tsb (termasuk
     * `WHERE koperasi_id IS NULL` untuk user yang belum terikat koperasi
     * manapun — where() dengan value null otomatis jadi whereNull di
     * Laravel).
     */
    public static function scopeQuery(QueryBuilder|EloquentBuilder $query, string $column = 'koperasi_id'): QueryBuilder|EloquentBuilder
    {
        $user = Auth::user();

        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where($column, $user->koperasi_id);
    }

    /**
     * True kalau ada user login yang benar-benar terikat satu koperasi
     * (bukan super_admin, bukan tamu). Dipakai model/service yang murni
     * konfigurasi per-koperasi tanpa versi "global" yang masuk akal (mis.
     * IdentitasAplikasiService) — beda dari scopeQuery()/KoperasiScope yang
     * justru sengaja TIDAK memfilter untuk super_admin karena dipakai data
     * bisnis lintas-koperasi yang memang boleh dilihat semuanya.
     */
    public static function hasTenant(): bool
    {
        $user = Auth::user();

        return $user !== null && ! $user->isSuperAdmin();
    }

    /**
     * True hanya kalau ADA user login dan dia super_admin — beda dari
     * `! hasTenant()` yang juga true untuk kondisi tidak ada user sama
     * sekali (artisan/seeder/test tanpa actingAs). Dipakai guard tulis pada
     * setting yang dampaknya ke perhitungan bisnis (format kode barang, hari
     * operasional) supaya panggilan service langsung tanpa sesi (console)
     * tetap berjalan seperti sebelumnya, tapi super_admin yang benar-benar
     * login tetap dicegah menimpa baris pengaturan koperasi lain.
     */
    public static function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }
}
