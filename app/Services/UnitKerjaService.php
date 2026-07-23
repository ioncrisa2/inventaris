<?php

namespace App\Services;

use App\Models\UnitKerja;
use App\Repositories\UnitKerjaRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitKerjaService
{
    public function __construct(private UnitKerjaRepository $unitKerjaRepository) {}

    public function list(?string $search, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->unitKerjaRepository->paginate($search, $perPage);
    }

    public function store(array $data): UnitKerja
    {
        return DB::transaction(fn () => $this->unitKerjaRepository->create($data), 3);
    }

    public function update(UnitKerja $unitKerja, array $data): UnitKerja
    {
        return DB::transaction(fn () => $this->unitKerjaRepository->update($unitKerja, $data), 3);
    }

    public function destroy(UnitKerja $unitKerja): void
    {
        $this->destroyMany([$unitKerja->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $unitKerjas = $this->unitKerjaRepository->findManyForDelete($ids);

            if ($ids === [] || $unitKerjas->count() !== count($ids)) {
                throw new \DomainException('Sebagian unit kerja sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $unitKerjas->each(fn (UnitKerja $unitKerja) => $this->ensureCanDelete($unitKerja));
            $unitKerjas->each(fn (UnitKerja $unitKerja) => $this->unitKerjaRepository->delete($unitKerja));

            return $unitKerjas->count();
        }, 3);
    }

    public function ensureCanDelete(UnitKerja $unitKerja): void
    {
        $atribut = $unitKerja->getAttributes();
        $memilikiRelasi = array_key_exists('karyawan_exists', $atribut)
            ? (bool) ($unitKerja->karyawan_exists || $unitKerja->barang_exists || $unitKerja->user_exists)
            : ($unitKerja->karyawan()->exists() || $unitKerja->barang()->exists() || $unitKerja->user()->exists());

        if ($memilikiRelasi) {
            throw new \DomainException('Unit kerja tidak dapat dihapus karena masih terhubung dengan karyawan, barang, atau pengguna. Pindahkan data terkait terlebih dahulu.');
        }
    }
}
