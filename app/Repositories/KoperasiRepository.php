<?php

namespace App\Repositories;

use App\Models\Koperasi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KoperasiRepository
{
    public function paginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return Koperasi::query()
            ->with('adminPrimerRole:id,koperasi_id')
            ->withCount('users')
            ->when($search, fn ($query, $search) => $query->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Koperasi
    {
        return Koperasi::create($data);
    }

    public function update(Koperasi $koperasi, array $data): Koperasi
    {
        $koperasi->update($data);

        return $koperasi;
    }
}
