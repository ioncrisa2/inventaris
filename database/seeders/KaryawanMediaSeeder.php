<?php

namespace Database\Seeders;

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Services\TransactionalFileStorage;
use Database\Seeders\Support\DemoMedia;
use Illuminate\Database\Seeder;

class KaryawanMediaSeeder extends Seeder
{
    private TransactionalFileStorage $files;

    public function run(): void
    {
        $this->files = app(TransactionalFileStorage::class);
        $colors = ['#1d4ed8', '#047857', '#7c3aed', '#b45309', '#be123c', '#0f766e'];
        $karyawans = Karyawan::orderBy('nik')->get();

        foreach ($karyawans as $index => $karyawan) {
            if ($index < 12) {
                $fotoPath = "demo/karyawan/{$karyawan->nik}.svg";
                $this->files->put(
                    'public',
                    $fotoPath,
                    DemoMedia::svg($karyawan->nama_lengkap, $colors[$index % count($colors)]),
                );
                $karyawan->update(['foto_karyawan' => $fotoPath]);
            }

            if ($index % 5 !== 4) {
                $this->simpanDokumen($karyawan, 'KTP', "ktp-{$karyawan->nik}.pdf");
            }

            if ($index % 2 === 0) {
                $this->simpanDokumen($karyawan, 'Ijazah', "ijazah-{$karyawan->nik}.pdf");
            }
        }
    }

    private function simpanDokumen(Karyawan $karyawan, string $jenis, string $nama): void
    {
        $path = "demo/dokumen-karyawan/{$nama}";
        $this->files->put('local', $path, DemoMedia::pdf("{$jenis} - {$karyawan->nama_lengkap}"));
        DokumenKaryawan::updateOrCreate(
            ['karyawan_id' => $karyawan->id, 'jenis_dokumen' => $jenis],
            ['nama_asli' => $nama, 'path' => $path],
        );
    }
}
