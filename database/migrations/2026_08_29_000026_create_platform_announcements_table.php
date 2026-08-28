<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_koperasi_id')->nullable()->constrained('koperasi')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('priority', 20)->default('info');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['target_koperasi_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcements');
    }
};
