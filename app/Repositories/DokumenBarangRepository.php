<?php

namespace App\Repositories;

use App\Models\Barang;
use App\Models\DokumenBarang;
use Illuminate\Support\Collection;

class DokumenBarangRepository
{
    public function terbaruUntuk(Barang $barang): Collection
    {
        return $barang->dokumen()->latest()->get();
    }

    public function create(Barang $barang, array $data): DokumenBarang
    {
        return $barang->dokumen()->create($data);
    }

    public function delete(DokumenBarang $dokumen): void
    {
        $dokumen->delete();
    }
}
