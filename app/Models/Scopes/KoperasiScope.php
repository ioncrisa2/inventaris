<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class KoperasiScope implements Scope
{
    /**
     * Batasi query ke koperasi milik user yang login. Tidak berlaku untuk
     * super_admin (lihat lintas koperasi) atau saat tidak ada user yang
     * login (mis. perintah artisan/seeder) — di kedua kasus itu query
     * berjalan tanpa filter tenant.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $builder->where($model->qualifyColumn('koperasi_id'), $user->koperasi_id);
    }
}
