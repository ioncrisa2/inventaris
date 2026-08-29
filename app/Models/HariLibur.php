<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = ['tanggal', 'keterangan'];

    protected $hidden = ['cakupan_id'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];

    /**
     * Hari libur memiliki dua lapis dalam satu tabel:
     * - koperasi_id null: baseline nasional yang berlaku untuk semua primer;
     * - koperasi_id terisi: hari libur tambahan milik satu primer.
     *
     * Tenant melihat gabungan baseline + tambahannya. Jika data lama tenant
     * kebetulan sama tanggal dengan baseline, baseline menang dan hanya satu
     * baris yang ditampilkan/dipakai dalam perhitungan.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('hari_libur_efektif', function (Builder $builder) {
            $user = auth()->user();

            if (! $user || $user->isSuperAdmin()) {
                return;
            }

            if ($user->koperasi_id === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $table = $builder->getModel()->getTable();
            $koperasiId = (int) $user->koperasi_id;

            $builder->where(function (Builder $query) use ($table, $koperasiId) {
                $query->whereNull("{$table}.koperasi_id")
                    ->orWhere(function (Builder $query) use ($table, $koperasiId) {
                        $query->where("{$table}.koperasi_id", $koperasiId)
                            ->whereNotExists(function ($subquery) use ($table) {
                                $subquery->selectRaw('1')
                                    ->from('hari_libur as baseline_hari_libur')
                                    ->whereNull('baseline_hari_libur.koperasi_id')
                                    ->whereColumn('baseline_hari_libur.tanggal', "{$table}.tanggal");
                            });
                    });
            });
        });

        static::saving(function (HariLibur $hariLibur) {
            $user = auth()->user();

            if (! $user) {
                $hariLibur->cakupan_id = $hariLibur->koperasi_id === null
                    ? 0
                    : (int) $hariLibur->koperasi_id;

                return;
            }

            if ($user->isSuperAdmin()) {
                if ($hariLibur->koperasi_id !== null) {
                    throw new AuthorizationException('Super admin hanya dapat menulis baseline hari libur nasional.');
                }

                $hariLibur->cakupan_id = 0;

                return;
            }

            if ($hariLibur->koperasi_id === null) {
                $hariLibur->koperasi_id = $user->koperasi_id;
            }

            if ($user->koperasi_id === null
                || (int) $hariLibur->koperasi_id !== (int) $user->koperasi_id) {
                throw new AuthorizationException('Hari libur tambahan tidak berada dalam koperasi aktif Anda.');
            }

            $hariLibur->cakupan_id = (int) $user->koperasi_id;
        });
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }

    public function isBaselineNasional(): bool
    {
        return $this->koperasi_id === null;
    }
}
