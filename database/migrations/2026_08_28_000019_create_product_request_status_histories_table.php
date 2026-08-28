<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_request_id', 'created_at'], 'pr_status_history_request_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_request_status_histories');
    }
};
