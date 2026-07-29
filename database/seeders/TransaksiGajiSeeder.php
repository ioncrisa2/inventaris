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
        $komponenList = KomponenGaji::orderBy('id')->get();

        if ($komponenList->isEmpty()) {
            throw new \RuntimeException('Komponen gaji wajib tersedia sebelum TransaksiGajiSeeder dijalankan.');
        }

        $periodeAwal = CarbonImmutable::now()->day >= 25
            ? CarbonImmutable::now()->startOfMonth()
            : CarbonImmutable::now()->subMonth()->startOfMonth();
        $karyawans = Karyawan::whereNull('tanggal_mengundurkan_diri')->orderBy('nik')->get();

        foreach ($karyawans as $karyawan) {
            foreach (range(0, 2) as $mundur) {
                $periode = $periodeAwal->subMonths($mundur);
                $baris = $komponenList->mapWithKeys(function (KomponenGaji $komponen) use ($periode) {
                    $row = ['pakai' => '1'];

                    if ($komponen->metode_perhitungan === 'per_hari') {
                        $row['tanggal_awal'] = $periode->startOfMonth()->toDateString();
                        $row['tanggal_akhir'] = $periode->endOfMonth()->toDateString();
                    }

                    return ["master_{$komponen->id}" => $row];
                })->all();
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
