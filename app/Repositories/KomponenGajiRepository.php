<?php

namespace App\Repositories;

use App\Models\KomponenGaji;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KomponenGajiRepository
{
    /**
     * @param  array{search?: ?string, jenis?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return KomponenGaji::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('nama_komponen', 'like', '%'.$search.'%');
            })
            ->when($filters['jenis'] ?? null, function ($query, $jenis) {
                $query->where('jenis', $jenis);
            })
            ->orderBy('jenis')
            ->orderBy('nama_komponen')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderedList(): Collection
    {
        return KomponenGaji::orderBy('jenis')->orderBy('nama_komponen')->get();
    }

    public function find(int $id): ?KomponenGaji
    {
        return KomponenGaji::find($id);
    }

    public function findManyForUpdate(array $ids): Collection
    {
        return KomponenGaji::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function findManyForDelete(array $ids): Collection
    {
        return KomponenGaji::query()
            ->withExists('transaksiGajiDetails')
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function create(array $data): KomponenGaji
    {
        return KomponenGaji::create($data);
    }

    public function update(KomponenGaji $komponenGaji, array $data): KomponenGaji
    {
        $komponenGaji->update($data);

        return $komponenGaji;
    }

    public function delete(KomponenGaji $komponenGaji): void
    {
        $komponenGaji->delete();
    }
}
