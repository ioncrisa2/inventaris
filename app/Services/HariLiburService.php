<?php

namespace App\Services;

use App\Imports\HariLiburImport;
use App\Models\HariLibur;
use App\Models\Koperasi;
use App\Repositories\HariLiburRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class HariLiburService
{
    public function __construct(private HariLiburRepository $hariLiburRepository) {}

    /**
     * @return array{ditambahkan: int, sudah_ada: int, gagal: int}
     */
    public function import(UploadedFile $file): array
    {
        $import = new HariLiburImport;

        DB::transaction(fn () => Excel::import($import, $file), 3);

        return [
            'ditambahkan' => $import->jumlahDitambahkan,
            'sudah_ada' => $import->jumlahSudahAda,
            'gagal' => $import->failures()->count(),
        ];
    }

    /**
     * @return Collection<int, array{tahun: int, jumlah: int}>
     */
    public function tahunList(?int $koperasiId = null): Collection
    {
        return $koperasiId
            ? $this->hariLiburRepository->tahunList($koperasiId)
            : $this->hariLiburRepository->tahunList();
    }

    /** @return Collection<int, Koperasi> */
    public function koperasiListUntukTahun(int $tahun): Collection
    {
        return $this->hariLiburRepository->koperasiListUntukTahun($tahun);
    }

    public function listForTahun(int $tahun, ?string $search, int $perPage = PerPage::DEFAULT, ?int $koperasiId = null): LengthAwarePaginator
    {
        return $koperasiId
            ? $this->hariLiburRepository->paginateForTahun($tahun, $search, $perPage, $koperasiId)
            : $this->hariLiburRepository->paginateForTahun($tahun, $search, $perPage);
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
