<?php

namespace App\Repositories;

use App\Models\UnitKerja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UnitKerjaRepository
{
    public function orderedList(): Collection
    {
        return UnitKerja::orderBy('nama_unit')->get();
    }

    public function find(int $id): ?UnitKerja
    {
        return UnitKerja::find($id);
    }

    public function findManyForDelete(array $ids): Collection
    {
        return UnitKerja::query()
            ->withExists(['karyawan', 'barang', 'user'])
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function paginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return UnitKerja::query()
            ->withCount(['karyawan', 'barang', 'user'])
            ->when($search, function ($query, $search) {
                $query->where('nama_unit', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): UnitKerja
    {
        return UnitKerja::create($data);
    }

    public function update(UnitKerja $unitKerja, array $data): UnitKerja
    {
        $unitKerja->update($data);

        return $unitKerja;
    }

    public function delete(UnitKerja $unitKerja): void
    {
        $unitKerja->delete();
    }
}
