<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_slip_gaji', function (Blueprint $table) {
            $table->unique('koperasi_id', 'template_slip_gaji_koperasi_unique');
        });
    }

    public function down(): void
    {
        Schema::table('template_slip_gaji', function (Blueprint $table) {
            $table->dropUnique('template_slip_gaji_koperasi_unique');
        });
    }
};
