<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('policy', 64);
            $table->nullableMorphs('owner');
            $table->string('collection', 80)->default('default');
            $table->string('status', 32)->default('ready')->index();
            $table->string('scan_status', 32)->default('not_required')->index();
            $table->string('staging_disk', 40)->nullable();
            $table->string('staging_path', 1024)->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path', 512)->nullable();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20);
            $table->unsignedBigInteger('source_size_bytes');
            $table->unsignedBigInteger('final_size_bytes')->nullable();
            $table->char('source_checksum_sha256', 64);
            $table->char('final_checksum_sha256', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['koperasi_id', 'policy']);
            $table->index(['koperasi_id', 'status']);
        });

        Schema::create('stored_file_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('disk', 40);
            $table->string('path', 512);
            $table->string('mime_type', 120);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['stored_file_id', 'name']);
            $table->index(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_file_variants');
        Schema::dropIfExists('stored_files');
    }
};
