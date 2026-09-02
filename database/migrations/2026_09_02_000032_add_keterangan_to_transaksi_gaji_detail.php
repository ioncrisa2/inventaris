<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->string('keterangan_snapshot')->nullable()->after('nama_komponen_snapshot');
        });

        DB::table('komponen_gaji')
            ->where('metode_perhitungan', 'harian_sehari')
            ->update(['metode_perhitungan' => 'harian_manual']);

        DB::table('transaksi_gaji_detail')
            ->where('metode_perhitungan_snapshot', 'harian_sehari')
            ->update([
                'metode_perhitungan_snapshot' => 'harian_manual',
                'jumlah_hari_snapshot' => DB::raw('COALESCE(jumlah_hari_snapshot, 1)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->dropColumn('keterangan_snapshot');
        });
    }
};
