<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hari_libur', function (Blueprint $table) {
            // 0 = baseline nasional, selain itu = ID koperasi pemilik data
            // tambahan. Kolom non-null ini menjaga tanggal baseline tetap
            // unik; unique nullable koperasi_id tidak menjamin itu di MySQL.
            $table->unsignedBigInteger('cakupan_id')->default(0)->after('koperasi_id');
        });

        DB::table('hari_libur')
            ->whereNotNull('koperasi_id')
            ->update(['cakupan_id' => DB::raw('koperasi_id')]);

        Schema::table('hari_libur', function (Blueprint $table) {
            // MySQL memakai unique lama sebagai index pendukung foreign key.
            // Sediakan penggantinya sebelum unique tersebut dilepas.
            $table->index(['koperasi_id', 'tanggal'], 'hari_libur_koperasi_tanggal_index');
        });

        Schema::table('hari_libur', function (Blueprint $table) {
            $table->dropUnique(['koperasi_id', 'tanggal']);
            $table->unique(['cakupan_id', 'tanggal'], 'hari_libur_cakupan_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hari_libur', function (Blueprint $table) {
            $table->dropUnique('hari_libur_cakupan_tanggal_unique');
            $table->unique(['koperasi_id', 'tanggal']);
        });

        Schema::table('hari_libur', function (Blueprint $table) {
            $table->dropIndex('hari_libur_koperasi_tanggal_index');
            $table->dropColumn('cakupan_id');
        });
    }
};
