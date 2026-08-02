<?php

namespace App\Models\Concerns;

use App\Models\Koperasi;
use App\Models\Scopes\KoperasiScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToKoperasi
{
    /**
     * Pasang scope tenant otomatis dan isi koperasi_id dari user tenant saat
     * record baru dibuat. Akun tanpa tenant aktif (termasuk super_admin)
     * tidak boleh menulis model operasional ini.
     */
    protected static function bootBelongsToKoperasi(): void
    {
        static::addGlobalScope(new KoperasiScope);

        // Global scope melindungi query baca. Guard saving ini melindungi jalur
        // tulis langsung/service agar model tenant tidak dapat dipindahkan ke
        // koperasi lain dengan mass assignment atau kode internal yang keliru.
        static::saving(function ($model) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if ($model->koperasi_id === null && ! $user->isSuperAdmin()) {
                $model->koperasi_id = $user->koperasi_id;
            }

            if ($user->isSuperAdmin()
                || $user->koperasi_id === null
                || (int) $model->koperasi_id !== (int) $user->koperasi_id) {
                throw new AuthorizationException('Data tidak berada dalam koperasi aktif Anda.');
            }
        });
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }
}
