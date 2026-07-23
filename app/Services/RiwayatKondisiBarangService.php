<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\RiwayatKondisiBarang;
use App\Repositories\RiwayatKondisiBarangRepository;
use Illuminate\Support\Facades\DB;

class RiwayatKondisiBarangService
{
    public function __construct(
        private RiwayatKondisiBarangRepository $riwayatKondisiBarangRepository,
        private DashboardCache $dashboardCache,
    ) {}

    public function catat(Barang $barang, array $data): RiwayatKondisiBarang
    {
        return DB::transaction(function () use ($barang, $data) {
            $riwayat = $this->riwayatKondisiBarangRepository->create($barang, $data);
            $this->dashboardCache->invalidateAfterCommit();

            return $riwayat;
        }, 3);
    }

    /**
     * Contoh operator logika (&&): gabungan dua kondisi untuk menentukan pesan.
     */
    public function pesanUntuk(array $data): string
    {
        $biayaPerbaikan = $data['biaya_perbaikan'] ?? 0;

        $kondisiRusakBerat = $data['kondisi'] === 'Rusak Berat';
        $biayaPerbaikanBesar = $biayaPerbaikan > 5000000;

        if ($kondisiRusakBerat && $biayaPerbaikanBesar) {
            return 'Kondisi barang berhasil dicatat. Perhatian: kerusakan berat dengan biaya perbaikan besar, segera tindak lanjuti.';
        }

        return 'Kondisi barang berhasil dicatat.';
    }
}
