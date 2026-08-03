<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('nama_komponen');
            $table->enum('jenis', ['Tunjangan', 'Potongan']);
            $table->string('metode_perhitungan')->default('nominal_tetap');
            $table->decimal('nilai_default', 15, 2)->default(0);
            $table->string('dasar_persentase')->nullable();
            $table->timestamps();
        });

        Schema::create('transaksi_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['karyawan_id', 'bulan', 'tahun']);
            $table->index(['tahun', 'bulan'], 'transaksi_gaji_tahun_bulan_index');
        });

        Schema::create('transaksi_gaji_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_gaji_id')->constrained('transaksi_gaji')->cascadeOnDelete();
            $table->foreignId('komponen_gaji_id')->nullable()->constrained('komponen_gaji')->restrictOnDelete();
            $table->string('nama_komponen_snapshot');
            $table->enum('jenis_snapshot', ['Tunjangan', 'Potongan']);
            $table->string('metode_perhitungan_snapshot');
            $table->decimal('nilai_snapshot', 15, 2);
            $table->string('dasar_persentase_snapshot')->nullable();
            $table->date('tanggal_awal_snapshot')->nullable();
            $table->date('tanggal_akhir_snapshot')->nullable();
            $table->unsignedSmallInteger('jumlah_hari_snapshot')->nullable();
            $table->decimal('nominal_hasil', 15, 2);
            $table->timestamps();

            $table->index(['transaksi_gaji_id', 'jenis_snapshot'], 'detail_gaji_transaksi_jenis_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_gaji_detail');
        Schema::dropIfExists('transaksi_gaji');
        Schema::dropIfExists('komponen_gaji');
    }
};
