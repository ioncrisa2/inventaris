<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->index(['tanggal', 'status'], 'absensi_tanggal_status_index');
        });

        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->index(['tahun', 'bulan'], 'transaksi_gaji_tahun_bulan_index');
        });

        Schema::table('riwayat_kondisi_barang', function (Blueprint $table) {
            $table->index(['barang_id', 'tanggal_pemeriksaan'], 'riwayat_barang_tanggal_index');
        });

        Schema::table('dokumen_barang', function (Blueprint $table) {
            $table->index(['barang_id', 'jenis_dokumen'], 'dokumen_barang_jenis_index');
        });

        Schema::table('dokumen_karyawan', function (Blueprint $table) {
            $table->index(['karyawan_id', 'jenis_dokumen'], 'dokumen_karyawan_jenis_index');
        });

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->index(['transaksi_gaji_id', 'jenis_snapshot'], 'detail_gaji_transaksi_jenis_index');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->index('tanggal_perolehan', 'barang_tanggal_perolehan_index');
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->dropIndex('barang_tanggal_perolehan_index');
        });

        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->dropIndex('detail_gaji_transaksi_jenis_index');
        });

        Schema::table('dokumen_karyawan', function (Blueprint $table) {
            $table->dropIndex('dokumen_karyawan_jenis_index');
        });

        Schema::table('dokumen_barang', function (Blueprint $table) {
            $table->dropIndex('dokumen_barang_jenis_index');
        });

        Schema::table('riwayat_kondisi_barang', function (Blueprint $table) {
            $table->dropIndex('riwayat_barang_tanggal_index');
        });

        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->dropIndex('transaksi_gaji_tahun_bulan_index');
        });

        Schema::table('absensi', function (Blueprint $table) {
            $table->dropIndex('absensi_tanggal_status_index');
        });
    }
};
