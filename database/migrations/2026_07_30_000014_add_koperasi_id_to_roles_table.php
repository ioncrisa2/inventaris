<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->foreignId('koperasi_id')->nullable()->after('id')
                ->constrained('koperasi')->restrictOnDelete();
            $table->unique(['koperasi_id', 'name', 'guard_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['koperasi_id']);
            $table->dropUnique(['koperasi_id', 'name', 'guard_name']);
            $table->dropColumn('koperasi_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};
