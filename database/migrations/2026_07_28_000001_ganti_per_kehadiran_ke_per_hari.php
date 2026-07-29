<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('komponen_gaji')
            ->where('metode_perhitungan', 'per_kehadiran')
            ->update(['metode_perhitungan' => 'per_hari']);

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->renameColumn('jumlah_hadir_snapshot', 'jumlah_hari_snapshot');
        });

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->date('tanggal_awal_snapshot')->nullable()->after('dasar_persentase_snapshot');
            $table->date('tanggal_akhir_snapshot')->nullable()->after('tanggal_awal_snapshot');
        });

        DB::table('transaksi_gaji_detail')
            ->where('metode_perhitungan_snapshot', 'per_kehadiran')
            ->update(['metode_perhitungan_snapshot' => 'per_hari']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transaksi_gaji_detail')
            ->where('metode_perhitungan_snapshot', 'per_hari')
            ->update(['metode_perhitungan_snapshot' => 'per_kehadiran']);

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->dropColumn(['tanggal_awal_snapshot', 'tanggal_akhir_snapshot']);
        });

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->renameColumn('jumlah_hari_snapshot', 'jumlah_hadir_snapshot');
        });

        DB::table('komponen_gaji')
            ->where('metode_perhitungan', 'per_hari')
            ->update(['metode_perhitungan' => 'per_kehadiran']);
    }
};
