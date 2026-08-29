<?php

namespace App\Repositories;

use App\Models\HariLibur;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HariLiburRepository
{
    public function paginateForTahun(int $tahun, ?string $search, int $perPage = 10, ?int $koperasiId = null): LengthAwarePaginator
    {
        $query = $koperasiId !== null
            ? $this->queryEfektifUntukKoperasi($koperasiId)
            : $this->queryUntukAktorAktif();

        return $query
            ->with('koperasi:id,nama')
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
    public function tahunList(?int $koperasiId = null): Collection
    {
        $query = $koperasiId !== null
            ? $this->queryEfektifUntukKoperasi($koperasiId)
            : $this->queryUntukAktorAktif();

        return $query
            ->get(['tanggal'])
            ->groupBy(fn (HariLibur $hariLibur) => $hariLibur->tanggal->year)
            ->map(fn (Collection $group, int $tahun) => [
                'tahun' => $tahun,
                'jumlah' => $group->unique(fn (HariLibur $hariLibur) => $hariLibur->tanggal->toDateString())->count(),
            ])
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

    public function ada(Carbon $tanggal, ?int $koperasiId = null): bool
    {
        return $this->queryEfektif($koperasiId)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->exists();
    }

    /**
     * Satu query batch untuk seluruh tanggal libur nasional pada rentang
     * inklusif, dipakai kedua konsumen (Absensi & perhitungan Uang Makan)
     * supaya tidak query per hari.
     *
     * @return Collection<string, string> tanggal (Y-m-d) => keterangan
     */
    public function tanggalDalamRentang(Carbon $awal, Carbon $akhir, ?int $koperasiId = null): Collection
    {
        return $this->queryEfektif($koperasiId)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->orderByRaw('koperasi_id IS NOT NULL')
            ->get()
            ->mapWithKeys(fn (HariLibur $hariLibur) => [$hariLibur->tanggal->toDateString() => $hariLibur->keterangan]);
    }

    /** @return Collection<string, string> tanggal (Y-m-d) => keterangan */
    public function tanggalBaselineDalamRentang(Carbon $awal, Carbon $akhir): Collection
    {
        return $this->queryBaseline()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()
            ->mapWithKeys(fn (HariLibur $hariLibur) => [$hariLibur->tanggal->toDateString() => $hariLibur->keterangan]);
    }

    /**
     * Pengecualian tulis control-plane yang sempit untuk sinkronisasi API.
     * Setiap baris menjadi baseline global dan konflik tanggal diabaikan.
     *
     * @param  list<array{tanggal: string, keterangan: string, jenis: string}>  $items
     */
    public function insertMissingBaseline(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $sekarang = now();
        $rows = collect($items)
            ->map(fn (array $item) => [
                'koperasi_id' => null,
                'cakupan_id' => 0,
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

    private function queryUntukAktorAktif(): Builder
    {
        return auth()->user()?->isSuperAdmin()
            ? $this->queryBaseline()
            : HariLibur::query();
    }

    private function queryEfektif(?int $koperasiId): Builder
    {
        $koperasiId ??= CurrentTenant::id();

        return $koperasiId !== null
            ? $this->queryEfektifUntukKoperasi($koperasiId)
            : $this->queryBaseline();
    }

    private function queryBaseline(): Builder
    {
        return HariLibur::withoutGlobalScopes()->whereNull('koperasi_id');
    }

    private function queryEfektifUntukKoperasi(int $koperasiId): Builder
    {
        return HariLibur::withoutGlobalScopes()
            ->where(function (Builder $query) use ($koperasiId) {
                $query->whereNull('hari_libur.koperasi_id')
                    ->orWhere(function (Builder $query) use ($koperasiId) {
                        $query->where('hari_libur.koperasi_id', $koperasiId)
                            ->whereNotExists(function ($subquery) {
                                $subquery->selectRaw('1')
                                    ->from('hari_libur as baseline_hari_libur')
                                    ->whereNull('baseline_hari_libur.koperasi_id')
                                    ->whereColumn('baseline_hari_libur.tanggal', 'hari_libur.tanggal');
                            });
                    });
            });
    }
}
