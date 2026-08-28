<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['product_request_id', 'visibility', 'created_at'], 'pr_messages_request_visibility_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_request_messages');
    }
};
