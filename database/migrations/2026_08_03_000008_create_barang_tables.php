<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('kategori');
            $table->string('jenis_barang')->nullable();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->restrictOnDelete();
            $table->string('lokasi_penempatan')->default('Kantor Pusat');
            $table->string('keterangan_lokasi')->nullable();
            $table->date('tanggal_perolehan');
            $table->decimal('harga_perolehan', 15, 2)->default(0);
            $table->string('foto_sampul')->nullable();
            $table->timestamps();

            $table->unique(['koperasi_id', 'kode_barang']);
            $table->index('tanggal_perolehan', 'barang_tanggal_perolehan_index');
        });

        Schema::create('riwayat_kondisi_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->date('tanggal_pemeriksaan');
            $table->string('kondisi');
            $table->text('keterangan')->nullable();
            $table->decimal('biaya_perbaikan', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal_pemeriksaan'], 'riwayat_barang_tanggal_index');
        });

        Schema::create('foto_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->string('path');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('dokumen_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->string('jenis_dokumen');
            $table->string('nama_asli');
            $table->string('path');
            $table->timestamps();

            $table->index(['barang_id', 'jenis_dokumen'], 'dokumen_barang_jenis_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_barang');
        Schema::dropIfExists('foto_barang');
        Schema::dropIfExists('riwayat_kondisi_barang');
        Schema::dropIfExists('barang');
    }
};
