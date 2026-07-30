<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->text('alamat_ktp')->nullable()->after('jumlah_anak');
            $table->text('alamat_domisili')->nullable()->after('alamat_ktp');
        });

        DB::table('karyawan')->where('status_karyawan', 'Tetap')->update(['status_karyawan' => 'PKWTT']);
        DB::table('karyawan')->where('status_karyawan', 'Kontrak')->update(['status_karyawan' => 'PKWT']);
    }

    public function down(): void
    {
        DB::table('karyawan')->where('status_karyawan', 'PKWTT')->update(['status_karyawan' => 'Tetap']);
        DB::table('karyawan')->where('status_karyawan', 'PKWT')->update(['status_karyawan' => 'Kontrak']);

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['alamat_ktp', 'alamat_domisili']);
        });
    }
};
