<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceWithRestrict('riwayat_kondisi_barang');
        $this->replaceWithRestrict('foto_barang');
        $this->replaceWithRestrict('dokumen_barang');
    }

    public function down(): void
    {
        $this->replaceWithCascade('riwayat_kondisi_barang');
        $this->replaceWithCascade('foto_barang');
        $this->replaceWithCascade('dokumen_barang');
    }

    private function replaceWithRestrict(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['barang_id']);
            $table->foreign('barang_id')->references('id')->on('barang')->restrictOnDelete();
        });
    }

    private function replaceWithCascade(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['barang_id']);
            $table->foreign('barang_id')->references('id')->on('barang')->cascadeOnDelete();
        });
    }
};
