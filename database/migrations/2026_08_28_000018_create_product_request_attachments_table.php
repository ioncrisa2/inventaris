<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('product_request_messages')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->index(['product_request_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_request_attachments');
    }
};
