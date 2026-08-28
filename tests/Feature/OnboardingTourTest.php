<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Re-use helpers from Pest.php for consistent test user creation
// adminUser() = adminPrimerUser()
// staffUser() = user with Role::Staff in same koperasi
// superAdminUser() = global super_admin

test('admin primer tenant sees onboarding bootstrap and auto-start flag on dashboard', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-onboarding-tour', false)
        ->assertSee('data-onboarding-auto-start="1"', false)
        ->assertSee('data-onboarding-url', false)
        ->assertSee('data-onboarding-restart', false);
});

test('tenant staff user sees onboarding bootstrap and auto-start flag on dashboard', function () {
    $user = staffUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-onboarding-tour', false)
        ->assertDontSee('Ulangi tur aplikasi', false);
});

test('completed tour sets auto-start flag to 0 but retains restart link', function () {
    $user = adminUser();
    $user->onboarding_tour_finished_at = now();
    $user->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-onboarding-auto-start="0"', false)
        ->assertSee('data-onboarding-restart', false);
});

test('PATCH records completion timestamp only for authenticated tenant user', function () {
    $user = adminUser();
    $otherUser = adminUser();

    $this->actingAs($user)
        ->patch(route('onboarding.tour.finish'))
        ->assertNoContent();

    $dismissedAt = $user->fresh()->onboarding_tour_finished_at;
    expect($dismissedAt)->not->toBeNull()
        ->and($otherUser->fresh()->onboarding_tour_finished_at)->toBeNull();
});

test('repeated PATCH requests preserve original timestamp (idempotent)', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->patch(route('onboarding.tour.finish'))
        ->assertNoContent();

    $firstAt = $user->fresh()->onboarding_tour_finished_at;
    sleep(1);

    $this->actingAs($user)
        ->patch(route('onboarding.tour.finish'))
        ->assertNoContent();

    expect($user->fresh()->onboarding_tour_finished_at->eq($firstAt))->toBeTrue();
});

test('completion is isolated per user even within same koperasi', function () {
    $userA = adminUser();
    $userB = adminUser();

    $this->actingAs($userA)
        ->patch(route('onboarding.tour.finish'))
        ->assertNoContent();

    expect($userA->fresh()->onboarding_tour_finished_at)->not->toBeNull()
        ->and($userB->fresh()->onboarding_tour_finished_at)->toBeNull();
});

test('guest is redirected from completion endpoint', function () {
    $this->patch(route('onboarding.tour.finish'))
        ->assertRedirect('/login');
});

test('super admin does not see bootstrap element or restart link', function () {
    $user = superAdminUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-onboarding-tour', false)
        ->assertDontSee('data-onboarding-restart', false);
});

test('super admin PATCH request returns 403', function () {
    $user = superAdminUser();

    $this->actingAs($user)
        ->patch(route('onboarding.tour.finish'))
        ->assertForbidden();
});

test('restart link navigates to dashboard with fragment', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->get(route('dashboard').'#onboarding-tour')
        ->assertOk();
});
