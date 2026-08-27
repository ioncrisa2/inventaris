<?php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('migration grants role.create to existing admin primer roles', function () {
    $this->seed(PermissionSeeder::class);
    $adminPrimer = adminPrimerUser();
    $role = Role::where('name', 'admin_primer')
        ->where('koperasi_id', $adminPrimer->koperasi_id)
        ->firstOrFail();

    $role->revokePermissionTo('role.create');
    expect($role->fresh()->hasPermissionTo('role.create'))->toBeFalse();

    $migration = require database_path('migrations/2026_08_27_000014_grant_role_create_to_admin_primer_roles.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->hasPermissionTo('role.create'))->toBeTrue();
});
