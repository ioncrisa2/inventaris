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
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
            $table->unique(['koperasi_id', 'nik']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropForeign(['koperasi_id']);
            $table->dropUnique(['koperasi_id', 'nik']);
            $table->dropColumn('koperasi_id');
            $table->unique('nik');
        });
    }
};
