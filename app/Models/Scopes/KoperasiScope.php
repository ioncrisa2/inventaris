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

        // Akun tenant tanpa koperasi adalah kondisi data yang tidak sah. Scope
        // harus fail-closed; whereNull akan membuat akun itu melihat seluruh
        // record yatim yang koperasi_id-nya null.
        if ($user->koperasi_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('koperasi_id'), (int) $user->koperasi_id);
    }
}
