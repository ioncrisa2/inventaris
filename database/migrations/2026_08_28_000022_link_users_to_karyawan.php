<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('koperasi_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('karyawan_account_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawan')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['karyawan_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_account_audit_logs');

        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
