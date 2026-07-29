<?php

namespace App\Repositories;

use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransaksiGajiRepository
{
    /**
     * Daftar karyawan yang punya minimal satu transaksi gaji, beserta
     * jumlahnya — dipakai halaman index (cluster per karyawan).
     */
    public function karyawanList(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return Karyawan::query()
            ->whereHas('transaksiGaji')
            ->withCount('transaksiGaji')
            ->with('unitKerja:id,nama_unit')
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
