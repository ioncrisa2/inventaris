<?php

use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system role names are shown with a friendly label instead of the raw snake_case name', function () {
    $this->actingAs(superAdminUser());

    $this->get(route('role.index'))
        ->assertOk()
        ->assertSee('Super Admin')
        ->assertDontSee('super_admin');
});

test('custom tenant role names are shown as-is since they are already human readable', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Label']);
    $admin = adminPrimerUser($koperasi);

    $this->actingAs($admin);

    $this->get(route('pengguna.index'))
        ->assertOk()
        ->assertSee('Admin Primer')
        ->assertDontSee('admin_primer');
});
