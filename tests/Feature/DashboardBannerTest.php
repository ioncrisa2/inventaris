<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard shows a compact welcome banner until the user dismisses it', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang')
        ->assertSee(route('panduan-singkat'), false)
        ->assertSee('Panduan singkat')
        ->assertDontSee('welcomeModal', false)
        ->assertDontSee('welcome-feature-list', false);
});

test('dismissing the dashboard banner is stored for the authenticated user only', function () {
    $user = adminUser();
    $otherUser = adminUser();

    $this->actingAs($user)
        ->patch(route('dashboard.banner.dismiss'))
        ->assertRedirect(route('dashboard'));

    $dismissedAt = $user->fresh()->dashboard_banner_dismissed_at;

    expect($dismissedAt)->not->toBeNull()
        ->and($otherUser->fresh()->dashboard_banner_dismissed_at)->toBeNull();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('dashboard-tip', false)
        ->assertDontSee('Panduan singkat');

    $this->travel(5)->minutes();

    $this->patch(route('dashboard.banner.dismiss'))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->dashboard_banner_dismissed_at->equalTo($dismissedAt))->toBeTrue();

    $this->actingAs($otherUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('dashboard-tip', false);
});

test('dashboard banner actions and quick guide require authentication', function () {
    $this->patch(route('dashboard.banner.dismiss'))
        ->assertRedirect('/login');

    $this->get(route('panduan-singkat'))
        ->assertRedirect('/login');
});

test('dismissing the banner preserves the selected dashboard period', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->patch(route('dashboard.banner.dismiss'), ['periode' => '2026-05-25'])
        ->assertRedirect(route('dashboard', ['periode' => '2026-05-25']));

    expect($user->fresh()->dashboard_banner_dismissed_at)->not->toBeNull();
});

test('authenticated users can open the quick guide as a separate page', function () {
    $this->actingAs(adminUser())
        ->get(route('panduan-singkat'))
        ->assertOk()
        ->assertSee('Panduan Singkat')
        ->assertSee('Kembali ke Dashboard')
        ->assertSee('Cetak / Unduh PDF Panduan')
        ->assertSee('Proses penggajian dan laporan')
        ->assertSee('Created By : Yohanes Dwiki Septian')
        ->assertDontSee('welcomeModal', false)
        ->assertDontSee('dashboard-tip', false);
});

test('authenticated users can access the printable PDF guide page', function () {
    $this->actingAs(adminUser())
        ->get(route('panduan-singkat.cetak'))
        ->assertOk()
        ->assertSee('PUSAT KOPERASI KREDIT (PUSKOPDIT) HARAPAN SEJAHTERA (PHS) SUMATERA SELATAN')
        ->assertSee('Panduan Langkah Demi Langkah: Admin Pusat')
        ->assertSee('Panduan Langkah Demi Langkah: Admin Primer')
        ->assertSee('Yohanes Dwiki Septian');
});
