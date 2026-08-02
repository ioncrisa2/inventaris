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
        ->assertSee('IT');
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
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors('email', null, 'updateProfile');
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
