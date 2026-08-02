<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropUnique(['nomor_ktp']);
            $table->unique(['koperasi_id', 'nomor_ktp']);
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropUnique(['koperasi_id', 'nomor_ktp']);
            $table->unique('nomor_ktp');
        });
    }
};
