<?php

namespace App\Models\Concerns;

use App\Models\Koperasi;
use App\Models\Scopes\KoperasiScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToKoperasi
{
    /**
     * Pasang scope tenant otomatis, dan isi koperasi_id dari user yang
     * login saat record baru dibuat (kalau belum diisi eksplisit —
     * penyediaan koperasi baru oleh super_admin butuh set koperasi_id
     * tenant yang dituju, bukan koperasi_id super_admin sendiri).
     */
    protected static function bootBelongsToKoperasi(): void
    {
        static::addGlobalScope(new KoperasiScope);

        static::creating(function ($model) {
            if ($model->koperasi_id === null && auth()->check()) {
                $model->koperasi_id = auth()->user()->koperasi_id;
            }
        });
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }
}
