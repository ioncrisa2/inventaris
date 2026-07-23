<?php

namespace App\Repositories;

use App\Models\Karyawan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KaryawanRepository
{
    /**
     * @param  array{search?: ?string, unit_kerja_id?: ?string, status_karyawan?: ?string, kelengkapan?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Karyawan::query()
            ->with('unitKerja:id,nama_unit')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['status_karyawan'] ?? null, function ($query, $status) {
                $query->where('status_karyawan', $status);
            })
            ->when(($filters['kelengkapan'] ?? null) === 'data-inti', function ($query) {
                $query->whereNull('tanggal_mengundurkan_diri')
                    ->where(function ($query) {
                        $query->whereNull('tanggal_masuk_kerja')
                            ->orWhereNull('foto_karyawan')
                            ->orWhere('foto_karyawan', '')
                            ->orWhereNull('nomor_ktp')
                            ->orWhere('nomor_ktp', '')
                            ->orWhereDoesntHave('dokumen', fn ($dokumen) => $dokumen->where('jenis_dokumen', 'KTP'));
                    });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderedList(): Collection
    {
        return Karyawan::with('unitKerja:id,nama_unit')->orderBy('nama_lengkap')->get();
    }

    public function findOrFail(int $id): Karyawan
    {
        return Karyawan::findOrFail($id);
    }

    public function findOrFailForUpdate(int $id): Karyawan
    {
        return Karyawan::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findManyForDelete(array $ids): Collection
    {
        return Karyawan::query()
            ->with('dokumen:id,karyawan_id,path')
            ->withExists(['absensis', 'transaksiGaji', 'bawahanLangsung'])
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function find(int $id): ?Karyawan
    {
        return Karyawan::find($id);
    }

    public function searchOrderedByName(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return Karyawan::query()
            ->with('unitKerja:id,nama_unit')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Karyawan
    {
        return Karyawan::create($data);
    }

    public function update(Karyawan $karyawan, array $data): Karyawan
    {
        $karyawan->update($data);

        return $karyawan;
    }

    public function delete(Karyawan $karyawan): void
    {
        $karyawan->delete();
    }
}
