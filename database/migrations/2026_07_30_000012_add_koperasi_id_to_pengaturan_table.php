<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom koperasi_id ditambah dulu sebagai persiapan, TANPA mengubah unique
     * constraint `key` menjadi composite (koperasi_id, key). `KodeBarangGenerator`
     * masih memakai `Pengaturan::insertOrIgnore()` sebagai mutex yang mengandalkan
     * `key` unik sendiri — kalau constraint-nya diubah jadi composite sebelum kode
     * aplikasi diperbarui untuk selalu mengisi koperasi_id (Fase berikutnya), baris
     * dengan koperasi_id NULL tidak akan saling mencegah duplikat (NULL tidak
     * dianggap sama dengan NULL di unique index), sehingga mutex-nya bocor.
     * Constraint composite baru akan ditambahkan bersamaan saat Pengaturan
     * benar-benar di-scope per koperasi.
     */
    public function up(): void
    {
        Schema::table('pengaturan', function (Blueprint $table) {
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengaturan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('koperasi_id');
        });
    }
};
