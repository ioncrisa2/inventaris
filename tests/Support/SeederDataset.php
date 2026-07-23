<?php

namespace Tests\Support;

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Pengaturan;
use App\Models\RiwayatKondisiBarang;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SeederDataset
{
    public static function counts(): array
    {
        return [
            'permissions' => Permission::count(),
            'roles' => Role::count(),
            'users' => User::count(),
            'units' => UnitKerja::count(),
            'employees' => Karyawan::count(),
            'attendance' => Absensi::count(),
            'assets' => Barang::count(),
            'conditions' => RiwayatKondisiBarang::count(),
            'employee_documents' => DokumenKaryawan::count(),
            'supporting_photos' => FotoBarang::count(),
            'asset_documents' => DokumenBarang::count(),
            'salary_components' => KomponenGaji::count(),
            'payrolls' => TransaksiGaji::count(),
            'payroll_details' => TransaksiGajiDetail::count(),
            'settings' => Pengaturan::count(),
        ];
    }
}
