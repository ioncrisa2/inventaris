<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('status')->default('Hadir');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal']);
            $table->index(['tanggal', 'status'], 'absensi_tanggal_status_index');
        });

        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('keterangan');
            $table->timestamps();

            $table->unique(['koperasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('absensi');
    }
};
