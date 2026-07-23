<?php

namespace App\Repositories;

use App\Models\Barang;
use App\Models\RiwayatKondisiBarang;
use Illuminate\Support\Collection;

class RiwayatKondisiBarangRepository
{
    public function terbaruUntuk(Barang $barang): Collection
    {
        return $barang->riwayatKondisi()
            ->latest('tanggal_pemeriksaan')
            ->latest('id')
            ->get();
    }

    public function create(Barang $barang, array $data): RiwayatKondisiBarang
    {
        return $barang->riwayatKondisi()->create($data);
    }
}
