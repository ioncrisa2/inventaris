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
            $table->string('status_karyawan')->change();

            // Identitas
            $table->string('tempat_lahir')->nullable()->after('nama_lengkap');
            $table->string('jenis_kelamin')->nullable()->after('tanggal_lahir');
            $table->string('agama')->nullable()->after('jenis_kelamin');
            $table->string('status_perkawinan')->nullable()->after('agama');
            $table->string('nomor_ktp', 16)->nullable()->unique()->after('status_perkawinan');
            $table->string('npwp')->nullable()->after('nomor_ktp');
            $table->string('pendidikan_terakhir')->nullable()->after('npwp');
            $table->string('jurusan')->nullable()->after('pendidikan_terakhir');
            $table->string('nama_sekolah')->nullable()->after('jurusan');
            $table->unsignedSmallInteger('tahun_lulus')->nullable()->after('nama_sekolah');
            $table->string('nama_pasangan')->nullable()->after('tahun_lulus');
            $table->unsignedTinyInteger('jumlah_anak')->nullable()->after('nama_pasangan');
            $table->date('tanggal_mengundurkan_diri')->nullable()->after('jumlah_anak');
            $table->string('foto_karyawan')->nullable()->after('tanggal_mengundurkan_diri');

            // Kepegawaian
            $table->date('tanggal_masuk_kerja')->nullable()->after('unit_kerja_id');
            $table->string('nomor_sk_pengangkatan')->nullable()->after('status_karyawan');
            $table->date('tanggal_sk_pengangkatan')->nullable()->after('nomor_sk_pengangkatan');
            $table->foreignId('atasan_langsung_id')->nullable()->after('tanggal_sk_pengangkatan')->constrained('karyawan')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_langsung_id');
            $table->dropColumn([
                'tempat_lahir', 'jenis_kelamin', 'agama', 'status_perkawinan', 'nomor_ktp', 'npwp',
                'pendidikan_terakhir', 'jurusan', 'nama_sekolah', 'tahun_lulus', 'nama_pasangan',
                'jumlah_anak', 'tanggal_mengundurkan_diri', 'foto_karyawan',
                'tanggal_masuk_kerja', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan',
            ]);
            $table->enum('status_karyawan', ['Tetap', 'Kontrak', 'Resign'])->change();
        });
    }
};
