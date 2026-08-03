<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->string('jenis_dokumen');
            $table->string('nama_asli');
            $table->string('path');
            $table->timestamps();

            $table->index(['karyawan_id', 'jenis_dokumen'], 'dokumen_karyawan_jenis_index');
        });

        Schema::create('riwayat_karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pelaku_snapshot');
            $table->string('jenis_perubahan');
            $table->date('tanggal_berlaku');
            $table->text('alasan');
            $table->string('sumber')->default('aksi_perubahan');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['karyawan_id', 'tanggal_berlaku']);
            $table->index(['karyawan_id', 'created_at']);
        });

        Schema::create('riwayat_karyawan_perubahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riwayat_karyawan_id')->constrained('riwayat_karyawan')->cascadeOnDelete();
            $table->string('field');
            $table->string('label');
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
            $table->text('tampilan_lama')->nullable();
            $table->text('tampilan_baru')->nullable();
            $table->boolean('sensitif')->default(false);

            $table->unique(['riwayat_karyawan_id', 'field'], 'riwayat_karyawan_field_unique');
            $table->index(['field', 'riwayat_karyawan_id']);
        });

        Schema::create('dokumen_riwayat_karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riwayat_karyawan_id')->constrained('riwayat_karyawan')->cascadeOnDelete();
            $table->string('nama_asli');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_riwayat_karyawan');
        Schema::dropIfExists('riwayat_karyawan_perubahan');
        Schema::dropIfExists('riwayat_karyawan');
        Schema::dropIfExists('dokumen_karyawan');
    }
};
