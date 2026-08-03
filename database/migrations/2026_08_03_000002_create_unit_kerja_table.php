<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->string('nama_unit');
            $table->string('kode', 10)->nullable();
            $table->timestamps();

            $table->unique(['koperasi_id', 'nama_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};
