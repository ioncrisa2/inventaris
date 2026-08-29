<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pengali_snapshot')->nullable()->after('jumlah_hari_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_gaji_detail', function (Blueprint $table) {
            $table->dropColumn('jumlah_pengali_snapshot');
        });
    }
};
