<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system owner dapat mengaktifkan maintenance dan tetap mengakses area owner', function () {
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->put(route('owner.maintenance.update'), [
            'message' => 'Pembaruan keamanan sedang berlangsung.',
            'starts_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->get(route('owner.maintenance.edit'))
        ->assertOk()
        ->assertSee('Pembaruan keamanan sedang berlangsung.');

    $this->assertDatabaseHas('platform_maintenance_audit_logs', [
        'actor_user_id' => $owner->id,
        'action' => 'enabled',
    ]);
});

test('maintenance aktif memblokir tenant dan super admin tetapi menyediakan login dan logout', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)->put(route('owner.maintenance.update'), [
        'message' => 'Sistem sedang diperbarui.',
    ])->assertRedirect();

    $tenant = adminPrimerUser();
    $this->actingAs($tenant)
        ->get(route('dashboard'))
        ->assertStatus(503)
        ->assertSee('Sistem sedang diperbarui.');

    $superAdmin = superAdminUser();
    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertStatus(503);

    $this->post(route('logout'))->assertRedirect();
    $this->get(route('login'))->assertOk();
});

test('system owner dapat menonaktifkan maintenance dari ui', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)->put(route('owner.maintenance.update'), [
        'message' => 'Maintenance singkat.',
    ])->assertRedirect();

    $this->delete(route('owner.maintenance.destroy'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('platform_maintenance_settings', [
        'id' => 1,
        'enabled' => false,
    ]);
    $this->assertDatabaseHas('platform_maintenance_audit_logs', [
        'actor_user_id' => $owner->id,
        'action' => 'disabled',
    ]);
});

test('maintenance terjadwal belum memblokir sebelum waktu mulai', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)->put(route('owner.maintenance.update'), [
        'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
    ])->assertRedirect();

    $tenant = adminPrimerUser();
    $this->actingAs($tenant)->get(route('dashboard'))->assertOk();
});

test('response json maintenance tidak membocorkan data internal', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)->put(route('owner.maintenance.update'), [
        'message' => 'Pemeliharaan layanan.',
    ])->assertRedirect();

    $tenant = adminPrimerUser();
    $this->actingAs($tenant)
        ->getJson(route('dashboard'))
        ->assertStatus(503)
        ->assertExactJson([
            'message' => 'Pemeliharaan layanan.',
            'ends_at' => null,
        ]);
});
