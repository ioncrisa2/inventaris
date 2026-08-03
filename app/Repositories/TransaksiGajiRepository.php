<?php

namespace App\Repositories;

use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransaksiGajiRepository
{
    /**
     * Daftar karyawan yang punya minimal satu transaksi gaji, beserta
     * jumlahnya — dipakai halaman index (cluster per karyawan).
     */
    public function karyawanList(?string $search, int $perPage = 10, ?int $koperasiId = null): LengthAwarePaginator
    {
        return Karyawan::query()
            ->whereHas('transaksiGaji')
            ->withCount('transaksiGaji')
            ->with(['koperasi:id,nama', 'unitKerja:id,nama_unit'])
            ->when($koperasiId, fn ($query) => $query->where('koperasi_id', $koperasiId))
            ->when($search, function ($query, $search) {
                $query->where('nama_lengkap', 'like', "%{$search}%");
            })
            ->orderBy('nama_lengkap')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{bulan?: ?string, tahun?: ?string}  $filters
     */
    public function paginateForKaryawan(int $karyawanId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return TransaksiGaji::query()
            ->where('karyawan_id', $karyawanId)
            ->when($filters['bulan'] ?? null, function ($query, $bulan) {
                $query->where('bulan', (int) $bulan);
            })
            ->when($filters['tahun'] ?? null, function ($query, $tahun) {
                $query->where('tahun', (int) $tahun);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, TransaksiGaji>
     */
    public function forSlipPrintPeriod(int $bulan, int $tahun, int $limit): Collection
    {
        return TransaksiGaji::query()
            ->with('karyawan.unitKerja', 'details')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->orderBy(
                Karyawan::query()
                    ->select('nama_lengkap')
                    ->whereColumn('karyawan.id', 'transaksi_gaji.karyawan_id'),
            )
            ->orderBy('transaksi_gaji.id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, TransaksiGaji>
     */
    public function forSlipPrintEmployeeRange(
        int $karyawanId,
        int $tahunAwal,
        int $bulanAwal,
        int $tahunAkhir,
        int $bulanAkhir,
        int $limit,
    ): Collection {
        return TransaksiGaji::query()
            ->with('karyawan.unitKerja', 'details')
            ->where('karyawan_id', $karyawanId)
            ->where(function ($query) use ($tahunAwal, $bulanAwal) {
                $query->where('tahun', '>', $tahunAwal)
                    ->orWhere(function ($query) use ($tahunAwal, $bulanAwal) {
                        $query->where('tahun', $tahunAwal)
                            ->where('bulan', '>=', $bulanAwal);
                    });
            })
            ->where(function ($query) use ($tahunAkhir, $bulanAkhir) {
                $query->where('tahun', '<', $tahunAkhir)
                    ->orWhere(function ($query) use ($tahunAkhir, $bulanAkhir) {
                        $query->where('tahun', $tahunAkhir)
                            ->where('bulan', '<=', $bulanAkhir);
                    });
            })
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): TransaksiGaji
    {
        return TransaksiGaji::create($data);
    }

    public function findOrFailForUpdate(int $id): TransaksiGaji
    {
        return TransaksiGaji::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function conflictingPeriodForUpdate(
        int $karyawanId,
        int $bulan,
        int $tahun,
        ?int $exceptId = null,
    ): ?TransaksiGaji {
        return TransaksiGaji::query()
            ->where('karyawan_id', $karyawanId)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->first();
    }

    public function update(TransaksiGaji $transaksiGaji, array $data): TransaksiGaji
    {
        $transaksiGaji->update($data);

        return $transaksiGaji;
    }

    public function delete(TransaksiGaji $transaksiGaji): void
    {
        $transaksiGaji->delete();
    }

    /**
     * Ganti seluruh baris detail transaksi (hapus lalu buat ulang), dipanggil
     * di dalam transaction yang sama dengan penyimpanan header.
     */
    public function replaceDetails(TransaksiGaji $transaksiGaji, array $rows): void
    {
        $transaksiGaji->details()->delete();
        $transaksiGaji->details()->createMany($rows);
    }
}
