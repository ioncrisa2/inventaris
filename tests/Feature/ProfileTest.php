<?php

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('profile page displays authenticated user data', function () {
    $user = adminUser([
        'name' => 'Admin Inventaris',
        'email' => 'admin@example.com',
    ]);
    $this->actingAs($user);
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
    $user->update(['unit_kerja_id' => $unitKerja->id]);

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Admin Inventaris')
        ->assertSee('admin@example.com')
        ->assertSee('IT')
        ->assertSee('data-bs-target="#confirmProfileUpdateModal"', false)
        ->assertSee('form="profileInformationForm"', false)
        ->assertSee('Konfirmasi &amp; Simpan', false)
        ->assertSeeInOrder([
            'aria-controls="current_password"',
            'aria-controls="password"',
            'aria-controls="password_confirmation"',
            'aria-controls="profile_current_password"',
        ], false);
});

test('profile information can be updated', function () {
    $user = adminUser([
        'name' => 'Admin Lama',
        'email' => 'lama@example.com',
    ]);
    $this->actingAs($user);
    $unitKerja = UnitKerja::create(['nama_unit' => 'Keuangan']);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Admin Baru',
            'email' => 'baru@example.com',
            'unit_kerja_id' => $unitKerja->id,
            'current_password' => 'password',
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('profile_success');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Admin Baru',
        'email' => 'baru@example.com',
        'unit_kerja_id' => $unitKerja->id,
    ]);
});

test('profile email must remain unique', function () {
    $user = adminUser(['email' => 'admin@example.com']);
    User::factory()->create(['email' => 'used@example.com']);

    $this->actingAs($user)
        ->from(route('profile.show'))
        ->put(route('profile.update'), [
            'name' => $user->name,
            'email' => 'used@example.com',
            'unit_kerja_id' => null,
            'current_password' => 'password',
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors('email', null, 'updateProfile');
});

test('profile information cannot be updated without the current password', function () {
    $user = adminUser([
        'name' => 'Admin Aman',
        'email' => 'aman@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->from(route('profile.show'))
        ->put(route('profile.update'), [
            'name' => 'Nama Disusupi',
            'email' => 'disusupi@example.com',
            'unit_kerja_id' => null,
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors('current_password', null, 'updateProfile');

    expect($user->fresh()->name)->toBe('Admin Aman')
        ->and($user->fresh()->email)->toBe('aman@example.com');
});

test('profile information cannot be updated with an incorrect password', function () {
    $user = adminUser([
        'name' => 'Admin Aman',
        'email' => 'aman@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->followingRedirects()
        ->from(route('profile.show'))
        ->put(route('profile.update'), [
            'name' => 'Nama Disusupi',
            'email' => 'disusupi@example.com',
            'unit_kerja_id' => null,
            'current_password' => 'password-salah',
        ])
        ->assertOk()
        ->assertSee('data-auto-show-modal', false)
        ->assertSee('Password saat ini tidak sesuai.')
        ->assertDontSee('value="password-salah"', false);

    expect($user->fresh()->name)->toBe('Admin Aman')
        ->and($user->fresh()->email)->toBe('aman@example.com');
});

test('password update requires the current password', function () {
    $user = adminUser(['password' => 'password']);

    $this->actingAs($user)
        ->from(route('profile.show').'#keamanan')
        ->put(route('profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
        ->assertRedirect(route('profile.show').'#keamanan')
        ->assertSessionHasErrors('current_password', null, 'updatePassword');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('password can be updated with valid credentials', function () {
    $user = adminUser(['password' => 'password']);

    $this->actingAs($user)
        ->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
        ->assertRedirect(route('profile.show').'#keamanan')
        ->assertSessionHas('password_success');

    expect(Hash::check('PasswordBaru123', $user->fresh()->password))->toBeTrue();
});
