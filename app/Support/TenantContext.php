<?php

namespace App\Support;

use App\Models\Koperasi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantContext
{
    public static function selectedKoperasiId(Request $request): ?int
    {
        if (! $request->user()?->isSuperAdmin()) {
            return null;
        }

        $value = $request->query('koperasi_id');

        if ($value === null || $value === '') {
            return null;
        }

        $validated = Validator::make(
            ['koperasi_id' => $value],
            ['koperasi_id' => ['required', 'integer', Rule::exists('koperasi', 'id')]],
            ['koperasi_id.exists' => 'Koperasi yang dipilih tidak tersedia.'],
        )->validate();

        return (int) $validated['koperasi_id'];
    }

    /** @return Collection<int, Koperasi> */
    public static function koperasiOptions(Request $request): Collection
    {
        if (! $request->user()?->isSuperAdmin()) {
            return collect();
        }

        return Koperasi::query()
            ->select(['id', 'nama'])
            ->orderBy('nama')
            ->get();
    }

    /**
     * @return array{koperasis: Collection<int, Koperasi>, selectedKoperasiId: ?int}
     */
    public static function filterViewData(Request $request, ?int $selectedKoperasiId = null): array
    {
        return [
            'koperasis' => self::koperasiOptions($request),
            'selectedKoperasiId' => $selectedKoperasiId,
        ];
    }
}
