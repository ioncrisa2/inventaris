<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('failure_code');
        });

        Schema::create('stored_file_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->uuid('file_uuid');
            $table->foreignId('koperasi_id')->nullable()->constrained('koperasi')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->char('ip_hash', 64)->nullable();
            $table->string('context', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['koperasi_id', 'created_at']);
            $table->index(['file_uuid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_file_audits');
        Schema::table('stored_files', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
