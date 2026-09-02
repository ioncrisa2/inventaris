<?php

namespace App\Repositories;

use App\Models\KomponenGaji;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KomponenGajiRepository
{
    /**
     * @param  array{search?: ?string, jenis?: ?string, koperasi_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return KomponenGaji::query()
            ->with('koperasi:id,nama')
            ->withCount('rincian')
            ->withSum('rincian as total_rincian_nominal', 'nominal')
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
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
        return KomponenGaji::with([
            'rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
        ])->orderBy('jenis')->orderBy('nama_komponen')->get();
    }

    public function find(int $id): ?KomponenGaji
    {
        return KomponenGaji::find($id);
    }

    public function findManyForUpdate(array $ids): Collection
    {
        return KomponenGaji::query()
            ->with([
                'rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
            ])
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

    public function replaceRincian(KomponenGaji $komponenGaji, array $rows): void
    {
        $komponenGaji->rincian()->delete();

        if ($rows !== []) {
            $komponenGaji->rincian()->createMany($rows);
        }

        $komponenGaji->load([
            'rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
        ]);
    }

    public function delete(KomponenGaji $komponenGaji): void
    {
        $komponenGaji->delete();
    }
}
