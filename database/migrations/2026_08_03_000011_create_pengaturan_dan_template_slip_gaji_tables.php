<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['koperasi_id', 'key']);
        });

        Schema::create('template_slip_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('nama')->default('Template Slip Gaji');
            $table->json('konfigurasi_draf');
            $table->json('konfigurasi_terbit')->nullable();
            $table->unsignedInteger('revisi_draf')->default(1);
            $table->unsignedInteger('revisi_terbit')->nullable();
            $table->foreignId('draf_diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diterbitkan_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_slip_gaji');
        Schema::dropIfExists('pengaturan');
    }
};
