<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('first application access redirects guests to login', function () {
    $this->get('/')
        ->assertRedirect('/login');

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login Sistem');
});

test('guests are redirected to login from protected pages', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/dashboard',
    '/unit-kerja',
    '/karyawan',
    '/barang',
    '/laporan/inventaris',
    '/laporan/kepegawaian',
    '/profile',
]);

test('users can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertRedirect('/dashboard')
        ->assertSessionMissing('show_welcome_modal');

    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('dashboard-tip', false)
        ->assertSee('Panduan singkat')
        ->assertDontSee('welcomeModal', false)
        ->assertDontSee('data-auto-show-modal', false);
});

test('users cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users are redirected away from login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/dashboard');
});

test('authenticated users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('unused incomplete authentication endpoints are not exposed', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/register',
    '/password/reset',
    '/password/confirm',
]);
