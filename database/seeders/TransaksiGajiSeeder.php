<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\TransaksiGaji;
use App\Services\TransaksiGajiService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TransaksiGajiSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(TransaksiGajiService::class);
        $baris = KomponenGaji::orderBy('id')->get()
            ->mapWithKeys(fn (KomponenGaji $komponen) => ["master_{$komponen->id}" => ['pakai' => '1']])
            ->all();

        if ($baris === []) {
            throw new \RuntimeException('Komponen gaji wajib tersedia sebelum TransaksiGajiSeeder dijalankan.');
        }

        $periodeAwal = CarbonImmutable::now()->day >= 25
            ? CarbonImmutable::now()->startOfMonth()
            : CarbonImmutable::now()->subMonth()->startOfMonth();
        $karyawans = Karyawan::whereNull('tanggal_mengundurkan_diri')->orderBy('nik')->get();

        foreach ($karyawans as $karyawan) {
            foreach (range(0, 2) as $mundur) {
                $periode = $periodeAwal->subMonths($mundur);
                $header = [
                    'karyawan_id' => $karyawan->id,
                    'bulan' => $periode->month,
                    'tahun' => $periode->year,
                ];
                $transaksi = TransaksiGaji::query()
                    ->where($header)
                    ->first();

                $transaksi
                    ? $service->update($transaksi, $header, $baris)
                    : $service->store($header, $baris);
            }
        }
    }
}
