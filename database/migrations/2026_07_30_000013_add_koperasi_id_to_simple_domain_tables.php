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
        Schema::table('absensi', function (Blueprint $table) {
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
        });

        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
        });

        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koperasi_id');
        });

        Schema::table('komponen_gaji', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koperasi_id');
        });

        Schema::table('absensi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koperasi_id');
        });
    }
};
