<?php

namespace App\Repositories;

use App\Models\HariLibur;
use App\Models\Koperasi;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HariLiburRepository
{
    public function paginateForTahun(int $tahun, ?string $search, int $perPage = 10, ?int $koperasiId = null): LengthAwarePaginator
    {
        return HariLibur::query()
            ->with('koperasi:id,nama')
            ->whereYear('tanggal', $tahun)
            ->when($koperasiId, fn ($query) => $query->where('koperasi_id', $koperasiId))
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
    public function tahunList(?int $koperasiId = null): Collection
    {
        return HariLibur::query()
            ->when($koperasiId, fn ($query) => $query->where('koperasi_id', $koperasiId))
            ->get(['tanggal'])
            ->groupBy(fn (HariLibur $hariLibur) => $hariLibur->tanggal->year)
            ->map(fn (Collection $group, int $tahun) => ['tahun' => $tahun, 'jumlah' => $group->count()])
            ->sortByDesc('tahun')
            ->values();
    }

    /** @return Collection<int, Koperasi> */
    public function koperasiListUntukTahun(int $tahun): Collection
    {
        return Koperasi::query()
            ->select(['id', 'nama', 'is_active'])
            ->withCount([
                'hariLibur as jumlah_hari_libur' => fn ($query) => $query->whereYear('tanggal', $tahun),
            ])
            ->orderBy('nama')
            ->get();
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

    /**
     * Query control-plane wajib menyebut koperasi tujuan secara eksplisit.
     *
     * @return Collection<string, string> tanggal (Y-m-d) => keterangan
     */
    public function tanggalDalamRentangUntukKoperasi(Carbon $awal, Carbon $akhir, int $koperasiId): Collection
    {
        return HariLibur::query()
            ->where('koperasi_id', $koperasiId)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()
            ->mapWithKeys(fn (HariLibur $hariLibur) => [$hariLibur->tanggal->toDateString() => $hariLibur->keterangan]);
    }

    /**
     * Pengecualian tulis control-plane yang sempit untuk sinkronisasi API.
     * Setiap baris diberi koperasi_id eksplisit dan konflik tanggal diabaikan.
     *
     * @param  list<array{tanggal: string, keterangan: string, jenis: string}>  $items
     */
    public function insertMissingUntukKoperasi(int $koperasiId, array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $sekarang = now();
        $rows = collect($items)
            ->map(fn (array $item) => [
                'koperasi_id' => $koperasiId,
                'tanggal' => $item['tanggal'],
                'keterangan' => $item['keterangan'],
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])
            ->all();

        return DB::table('hari_libur')->insertOrIgnore($rows);
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
