<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->string('metode_perhitungan')->default('nominal_tetap')->change();
        });

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->string('metode_perhitungan_snapshot')->change();
            $table->unsignedSmallInteger('jumlah_hadir_snapshot')->nullable()->after('dasar_persentase_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->dropColumn('jumlah_hadir_snapshot');
            $table->enum('metode_perhitungan_snapshot', ['nominal_tetap', 'persentase'])->change();
        });

        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->enum('metode_perhitungan', ['nominal_tetap', 'persentase'])->default('nominal_tetap')->change();
        });
    }
};
