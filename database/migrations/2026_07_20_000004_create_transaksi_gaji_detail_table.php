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
        Schema::create('transaksi_gaji_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_gaji_id')->constrained('transaksi_gaji')->cascadeOnDelete();
            $table->foreignId('komponen_gaji_id')->nullable()->constrained('komponen_gaji')->nullOnDelete();

            // Snapshot: nilai di bawah ini dibekukan saat transaksi dibuat/diubah, agar
            // riwayat gaji tidak ikut berubah walau data pada master komponen_gaji
            // diedit atau dihapus setelahnya.
            $table->string('nama_komponen_snapshot');
            $table->enum('jenis_snapshot', ['Tunjangan', 'Potongan']);
            $table->enum('metode_perhitungan_snapshot', ['nominal_tetap', 'persentase']);
            $table->decimal('nilai_snapshot', 15, 2);
            $table->string('dasar_persentase_snapshot')->nullable();
            $table->decimal('nominal_hasil', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_gaji_detail');
    }
};
