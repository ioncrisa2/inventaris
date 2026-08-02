<?php

use App\Models\Koperasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user whose koperasi has expired is redirected to the expired page', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Lewat Masa Aktif', 'expires_at' => now()->subDay()]);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('koperasi.expired'));
});

test('user whose koperasi is deactivated is redirected to the expired page', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Nonaktif', 'is_active' => false]);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('koperasi.expired'));
});

test('user whose koperasi is still active can access the app normally', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aktif', 'expires_at' => now()->addYear()]);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('non-super user without a koperasi is rejected fail-closed', function () {
    $user = User::factory()->create(['koperasi_id' => null]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertForbidden();
});

test('valid global super admin can access without a koperasi', function () {
    $this->actingAs(superAdminUser())
        ->get('/dashboard')
        ->assertOk();
});

test('blocked user can still see the expired page and log out', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Lewat Masa Aktif', 'expires_at' => now()->subDay()]);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);

    $this->actingAs($user)
        ->get(route('koperasi.expired'))
        ->assertOk()
        ->assertSee('Koperasi Lewat Masa Aktif');

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('user whose koperasi is active is redirected away from the expired page', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aktif', 'expires_at' => now()->addYear()]);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);

    $this->actingAs($user)
        ->get(route('koperasi.expired'))
        ->assertRedirect(route('dashboard'));
});
