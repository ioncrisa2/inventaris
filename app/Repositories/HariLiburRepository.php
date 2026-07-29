<?php

namespace App\Repositories;

use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HariLiburRepository
{
    public function paginateForTahun(int $tahun, ?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return HariLibur::query()
            ->whereYear('tanggal', $tahun)
            ->when($search, function ($query, $search) {
                $query->where('keterangan', 'like', '%'.$search.'%');
            })
            ->orderBy('tanggal')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Daftar tahun yang punya data hari libur, beserta jumlahnya. Tidak
     * perlu paginasi — jumlah tahun yang pernah diisi selalu kecil.
     *
     * @return Collection<int, array{tahun: int, jumlah: int}>
     */
    public function tahunList(): Collection
    {
        return HariLibur::query()
            ->get(['tanggal'])
            ->groupBy(fn (HariLibur $hariLibur) => $hariLibur->tanggal->year)
            ->map(fn (Collection $group, int $tahun) => ['tahun' => $tahun, 'jumlah' => $group->count()])
            ->sortByDesc('tahun')
            ->values();
    }

    public function find(int $id): ?HariLibur
    {
        return HariLibur::find($id);
    }

    public function findManyForDelete(array $ids): Collection
    {
        return HariLibur::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function ada(Carbon $tanggal): bool
    {
        return HariLibur::query()->whereDate('tanggal', $tanggal->toDateString())->exists();
    }

    /**
     * Satu query batch untuk seluruh tanggal libur nasional pada rentang
     * inklusif, dipakai kedua konsumen (Absensi & perhitungan Uang Makan)
     * supaya tidak query per hari.
     *
     * @return Collection<string, string> tanggal (Y-m-d) => keterangan
     */
    public function tanggalDalamRentang(Carbon $awal, Carbon $akhir): Collection
    {
        return HariLibur::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()
            ->mapWithKeys(fn (HariLibur $hariLibur) => [$hariLibur->tanggal->toDateString() => $hariLibur->keterangan]);
    }

    public function create(array $data): HariLibur
    {
        return HariLibur::create($data);
    }

    public function update(HariLibur $hariLibur, array $data): HariLibur
    {
        $hariLibur->update($data);

        return $hariLibur;
    }

    public function delete(HariLibur $hariLibur): void
    {
        $hariLibur->delete();
    }
}
