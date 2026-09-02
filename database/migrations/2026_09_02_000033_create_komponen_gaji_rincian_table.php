<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_gaji_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_gaji_id')->constrained('komponen_gaji')->cascadeOnDelete();
            $table->string('keterangan');
            $table->decimal('nominal', 15, 2);
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();

            $table->unique(['komponen_gaji_id', 'urutan'], 'komponen_gaji_rincian_urutan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_gaji_rincian');
    }
};
