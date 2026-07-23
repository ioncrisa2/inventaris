<?php

namespace App\Repositories;

use App\Models\Barang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BarangRepository
{
    /**
     * @param  array{search?: ?string, unit_kerja_id?: ?string, kategori?: ?string, kondisi?: ?string, kelengkapan?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Barang::query()
            ->with([
                'unitKerja:id,nama_unit',
                'kondisiTerakhir' => fn ($query) => $query->select([
                    'riwayat_kondisi_barang.id',
                    'riwayat_kondisi_barang.barang_id',
                    'riwayat_kondisi_barang.tanggal_pemeriksaan',
                    'riwayat_kondisi_barang.kondisi',
                ]),
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('kode_barang', 'like', "%{$search}%")
                        ->orWhere('nama_barang', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%");
                });
            })
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['kategori'] ?? null, function ($query, $kategori) {
                $query->where('kategori', $kategori);
            })
            ->when($filters['kondisi'] ?? null, function ($query, $kondisi) {
                $grup = config("inventaris.kondisi_grup.{$kondisi}");

                if (! $grup) {
                    return;
                }

                if ($kondisi === 'belum-diperiksa') {
                    $query->doesntHave('kondisiTerakhir');

                    return;
                }

                $query->whereHas('kondisiTerakhir', fn ($riwayat) => $riwayat->whereIn('kondisi', $grup['values']));
            })
            ->when($filters['kelengkapan'] ?? null, function ($query, $kelengkapan) {
                if ($kelengkapan === 'belum-diperiksa') {
                    $query->doesntHave('kondisiTerakhir');
                } elseif ($kelengkapan === 'tanpa-foto') {
                    $query->where(fn ($foto) => $foto->whereNull('foto_sampul')->orWhere('foto_sampul', ''));
                } elseif ($kelengkapan === 'tanpa-nota') {
                    $query->whereDoesntHave('dokumen', fn ($dokumen) => $dokumen->where('jenis_dokumen', 'Nota Pembelian'));
                }
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function count(): int
    {
        return Barang::count();
    }

    public function kodeExists(string $kodeBarang): bool
    {
        return Barang::where('kode_barang', $kodeBarang)->exists();
    }

    public function findManyForDelete(array $ids): Collection
    {
        return Barang::query()
            ->withExists(['riwayatKondisi', 'fotoPendukung', 'dokumen'])
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function create(array $data): Barang
    {
        return Barang::create($data);
    }

    public function update(Barang $barang, array $data): Barang
    {
        $barang->update($data);

        return $barang;
    }

    public function delete(Barang $barang): void
    {
        $barang->delete();
    }
}
