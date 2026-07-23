<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relasi bisnis independen harus ditolak oleh database ketika induknya
     * masih dipakai. Child aggregate seperti foto/dokumen barang dan detail
     * transaksi tetap cascade karena lifecycle-nya memang mengikuti induk.
     */
    public function up(): void
    {
        $this->replaceForeignKey('karyawan', 'unit_kerja_id', 'unit_kerja', 'restrict');
        $this->replaceForeignKey('barang', 'unit_kerja_id', 'unit_kerja', 'restrict');
        $this->replaceForeignKey('users', 'unit_kerja_id', 'unit_kerja', 'restrict');
        $this->replaceForeignKey('absensi', 'karyawan_id', 'karyawan', 'restrict');
        $this->replaceForeignKey('transaksi_gaji', 'karyawan_id', 'karyawan', 'restrict');
        $this->replaceForeignKey('karyawan', 'atasan_langsung_id', 'karyawan', 'restrict');
        $this->replaceForeignKey('transaksi_gaji_detail', 'komponen_gaji_id', 'komponen_gaji', 'restrict');
    }

    public function down(): void
    {
        $this->replaceForeignKey('karyawan', 'unit_kerja_id', 'unit_kerja', 'cascade');
        $this->replaceForeignKey('barang', 'unit_kerja_id', 'unit_kerja', 'cascade');
        $this->replaceForeignKey('users', 'unit_kerja_id', 'unit_kerja', 'null');
        $this->replaceForeignKey('absensi', 'karyawan_id', 'karyawan', 'cascade');
        $this->replaceForeignKey('transaksi_gaji', 'karyawan_id', 'karyawan', 'cascade');
        $this->replaceForeignKey('karyawan', 'atasan_langsung_id', 'karyawan', 'null');
        $this->replaceForeignKey('transaksi_gaji_detail', 'komponen_gaji_id', 'komponen_gaji', 'null');
    }

    private function replaceForeignKey(string $tableName, string $column, string $parentTable, string $onDelete): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($column, $parentTable, $onDelete) {
            $table->dropForeign([$column]);

            $foreign = $table->foreign($column)->references('id')->on($parentTable);

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'null' => $foreign->nullOnDelete(),
                default => $foreign->restrictOnDelete(),
            };
        });
    }
};
