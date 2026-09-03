<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('system_owner_audit_logs', 'activity_logs');
    }

    public function down(): void
    {
        Schema::rename('activity_logs', 'system_owner_audit_logs');
    }
};
