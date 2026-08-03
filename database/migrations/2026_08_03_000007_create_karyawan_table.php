<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('nik');
            $table->string('nama_lengkap');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir');
            $table->string('jenis_kelamin')->nullable();
            $table->string('agama')->nullable();
            $table->string('status_perkawinan')->nullable();
            $table->string('nomor_ktp', 16)->nullable();
            $table->string('npwp')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('nama_sekolah')->nullable();
            $table->unsignedSmallInteger('tahun_lulus')->nullable();
            $table->string('nama_pasangan')->nullable();
            $table->unsignedTinyInteger('jumlah_anak')->nullable();
            $table->text('alamat_ktp')->nullable();
            $table->text('alamat_domisili')->nullable();
            $table->date('tanggal_mengundurkan_diri')->nullable();
            $table->string('foto_karyawan')->nullable();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->restrictOnDelete();
            $table->date('tanggal_masuk_kerja')->nullable();
            $table->string('jabatan');
            $table->string('status_karyawan');
            $table->string('nomor_sk_pengangkatan')->nullable();
            $table->date('tanggal_sk_pengangkatan')->nullable();
            $table->foreignId('atasan_langsung_id')->nullable()->constrained('karyawan')->restrictOnDelete();
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['koperasi_id', 'nik']);
            $table->unique(['koperasi_id', 'nomor_ktp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
