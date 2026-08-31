<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feature_settings', function (Blueprint $table) {
            $table->string('feature_key', 80)->primary();
            $table->boolean('enabled')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_feature_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 80);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feature_key', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feature_audit_logs');
        Schema::dropIfExists('platform_feature_settings');
    }
};
