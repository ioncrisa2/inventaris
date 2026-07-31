<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menuntaskan dua hal yang sengaja ditunda di migration Fase 1:
     * - `pengaturan.key` akhirnya diubah jadi unique composite (koperasi_id,
     *   key) — aman sekarang karena KodeBarangGenerator::pengaturanTerkunci()
     *   sudah diperbaiki untuk selalu mengisi koperasi_id eksplisit saat
     *   insertOrIgnore (lihat commit yang sama).
     * - `template_slip_gaji` terlewat dari daftar tabel Fase 1 — tabel ini
     *   juga singleton per-tenant (satu baris per koperasi), butuh
     *   koperasi_id sama seperti pengaturan.
     */
    public function up(): void
    {
        Schema::table('pengaturan', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['koperasi_id', 'key']);
        });

        Schema::table('template_slip_gaji', function (Blueprint $table) {
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_slip_gaji', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koperasi_id');
        });

        Schema::table('pengaturan', function (Blueprint $table) {
            $table->dropForeign(['koperasi_id']);
            $table->dropUnique(['koperasi_id', 'key']);
            $table->unique('key');
            $table->foreign('koperasi_id')->references('id')->on('koperasi')->restrictOnDelete();
        });
    }
};
