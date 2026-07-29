<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Repositories\HariLiburRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HariLiburService
{
    public function __construct(private HariLiburRepository $hariLiburRepository) {}

    /**
     * @return Collection<int, array{tahun: int, jumlah: int}>
     */
    public function tahunList(): Collection
    {
        return $this->hariLiburRepository->tahunList();
    }

    public function listForTahun(int $tahun, ?string $search, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->hariLiburRepository->paginateForTahun($tahun, $search, $perPage);
    }

    public function store(array $data): HariLibur
    {
        return DB::transaction(fn () => $this->hariLiburRepository->create($data), 3);
    }

    public function update(HariLibur $hariLibur, array $data): HariLibur
    {
        return DB::transaction(fn () => $this->hariLiburRepository->update($hariLibur, $data), 3);
    }

    public function destroy(HariLibur $hariLibur): void
    {
        $this->destroyMany([$hariLibur->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $hariLiburs = $this->hariLiburRepository->findManyForDelete($ids);

            if ($ids === [] || $hariLiburs->count() !== count($ids)) {
                throw new \DomainException('Sebagian hari libur sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $hariLiburs->each(fn (HariLibur $hariLibur) => $this->hariLiburRepository->delete($hariLibur));

            return $hariLiburs->count();
        }, 3);
    }
}
