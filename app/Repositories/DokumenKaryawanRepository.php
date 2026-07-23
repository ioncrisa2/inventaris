<?php

namespace App\Repositories;

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use Illuminate\Support\Collection;

class DokumenKaryawanRepository
{
    public function terbaruUntuk(Karyawan $karyawan): Collection
    {
        return $karyawan->dokumen()->latest()->get();
    }

    public function create(Karyawan $karyawan, array $data): DokumenKaryawan
    {
        return $karyawan->dokumen()->create($data);
    }

    public function delete(DokumenKaryawan $dokumen): void
    {
        $dokumen->delete();
    }
}
