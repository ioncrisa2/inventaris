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
        Schema::table('barang', function (Blueprint $table) {
            $table->string('kategori')->change();
        });

        Schema::table('riwayat_kondisi_barang', function (Blueprint $table) {
            $table->string('kondisi')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->enum('kategori', ['Elektronik', 'Kendaraan', 'Furnitur'])->change();
        });

        Schema::table('riwayat_kondisi_barang', function (Blueprint $table) {
            $table->enum('kondisi', ['Baik', 'Rusak Ringan', 'Rusak Berat'])->change();
        });
    }
};
