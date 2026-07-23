<?php

namespace App\Services;

use App\Models\KomponenGaji;
use App\Repositories\KomponenGajiRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KomponenGajiService
{
    public function __construct(private KomponenGajiRepository $komponenGajiRepository) {}

    /**
     * @param  array{search?: ?string, jenis?: ?string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->komponenGajiRepository->paginate($filters);
    }

    public function store(array $data): KomponenGaji
    {
        return DB::transaction(
            fn () => $this->komponenGajiRepository->create($this->withDasarPersentase($data)),
            3,
        );
    }

    public function update(KomponenGaji $komponenGaji, array $data): KomponenGaji
    {
        return DB::transaction(
            fn () => $this->komponenGajiRepository->update($komponenGaji, $this->withDasarPersentase($data)),
            3,
        );
    }

    public function destroy(KomponenGaji $komponenGaji): void
    {
        $this->destroyMany([$komponenGaji->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $komponenGaji = $this->komponenGajiRepository->findManyForDelete($ids);

            if ($ids === [] || $komponenGaji->count() !== count($ids)) {
                throw new \DomainException('Sebagian komponen gaji sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $komponenGaji->each(fn (KomponenGaji $komponen) => $this->ensureCanDelete($komponen));
            $komponenGaji->each(fn (KomponenGaji $komponen) => $this->komponenGajiRepository->delete($komponen));

            return $komponenGaji->count();
        }, 3);
    }

    public function ensureCanDelete(KomponenGaji $komponenGaji): void
    {
        $atribut = $komponenGaji->getAttributes();
        $digunakan = array_key_exists('transaksi_gaji_details_exists', $atribut)
            ? (bool) $komponenGaji->transaksi_gaji_details_exists
            : $komponenGaji->transaksiGajiDetails()->exists();

        if ($digunakan) {
            throw new \DomainException('Komponen gaji tidak dapat dihapus karena sudah digunakan pada transaksi gaji.');
        }
    }

    /**
     * dasar_persentase tidak diambil dari input pengguna: nilainya ditentukan
     * otomatis dari metode_perhitungan supaya konsisten ("gaji_pokok" untuk
     * persentase, kosong untuk nominal tetap).
     */
    private function withDasarPersentase(array $data): array
    {
        $data['dasar_persentase'] = $data['metode_perhitungan'] === 'persentase'
            ? 'gaji_pokok'
            : null;

        return $data;
    }
}
