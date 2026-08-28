<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('koperasi_id')->constrained('koperasi')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('module', 80)->nullable();
            $table->string('title', 180);
            $table->text('description');
            $table->string('requester_priority', 24);
            $table->string('internal_priority', 24)->nullable();
            $table->string('status', 32);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('product_requests')->nullOnDelete();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['koperasi_id', 'status', 'last_activity_at']);
            $table->index(['created_by', 'last_activity_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
