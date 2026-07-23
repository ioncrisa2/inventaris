<?php

namespace App\Repositories;

use App\Models\Barang;
use App\Models\FotoBarang;
use Illuminate\Support\Collection;

class FotoBarangRepository
{
    public function terbaruUntuk(Barang $barang): Collection
    {
        return $barang->fotoPendukung()->latest()->get();
    }

    public function create(Barang $barang, array $data): FotoBarang
    {
        return $barang->fotoPendukung()->create($data);
    }

    public function delete(FotoBarang $foto): void
    {
        $foto->delete();
    }
}
