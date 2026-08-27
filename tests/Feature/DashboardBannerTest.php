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

    $this->get(route('panduan-singkat.cetak'))
        ->assertRedirect('/login');
});

test('dismissing the banner preserves the selected dashboard period', function () {
    $user = adminUser();

    $this->actingAs($user)
        ->patch(route('dashboard.banner.dismiss'), ['periode' => '2026-05-25'])
        ->assertRedirect(route('dashboard', ['periode' => '2026-05-25']));

    expect($user->fresh()->dashboard_banner_dismissed_at)->not->toBeNull();
});

test('admin primer sees a guide dedicated to their own koperasi context', function () {
    $adminPrimer = adminUser();

    $this->actingAs($adminPrimer)
        ->get(route('panduan-singkat'))
        ->assertOk()
        ->assertSee('Panduan Admin Primer')
        ->assertSee('Admin Primer '.$adminPrimer->koperasi->nama)
        ->assertSee('Siapkan fondasi koperasi')
        ->assertSee('Buat role custom koperasi')
        ->assertSee('Role otomatis terikat ke koperasi Anda')
        ->assertDontSee('Panduan Super Admin')
        ->assertDontSee('Seluruh koperasi')
        ->assertDontSee('Manajemen Koperasi')
        ->assertSee('Kembali ke Dashboard')
        ->assertSee('Cetak Panduan')
        ->assertSee('Created By : Yohanes Dwiki Septian')
        ->assertSee('+62 895 6049 5663 2')
        ->assertSee('zyohanes67@gmail.com')
        ->assertSeeInOrder(['Created By : Yohanes Dwiki Septian', '|', '+62 895 6049 5663 2', '|', 'zyohanes67@gmail.com'])
        ->assertDontSee('Dokumen:')
        ->assertDontSee('welcomeModal', false)
        ->assertDontSee('dashboard-tip', false);
});

test('admin primer printable guide excludes super admin instructions', function () {
    $adminPrimer = adminUser();

    $this->actingAs($adminPrimer)
        ->get(route('panduan-singkat.cetak'))
        ->assertOk()
        ->assertSee('Panduan Admin Primer')
        ->assertSee($adminPrimer->koperasi->nama)
        ->assertSee('Jalankan operasional harian')
        ->assertDontSee('Panduan Super Admin')
        ->assertDontSee('Seluruh koperasi')
        ->assertSee('Yohanes Dwiki Septian')
        ->assertSee('+62 895 6049 5663 2')
        ->assertSee('zyohanes67@gmail.com')
        ->assertDontSee('Dokumen:');
});

test('super admin sees a separate control plane guide', function () {
    $this->actingAs(superAdminUser())
        ->get(route('panduan-singkat'))
        ->assertOk()
        ->assertSee('Panduan Super Admin')
        ->assertSee('Seluruh koperasi')
        ->assertSee('Kelola siklus hidup koperasi')
        ->assertSee('Daftarkan koperasi baru')
        ->assertSee('Sinkronkan hari libur nasional')
        ->assertDontSee('Panduan Admin Primer')
        ->assertDontSee('Siapkan fondasi koperasi')
        ->assertSee('+62 895 6049 5663 2')
        ->assertSee('zyohanes67@gmail.com')
        ->assertDontSee('Dokumen:');

    $this->get(route('panduan-singkat.cetak'))
        ->assertOk()
        ->assertSee('Panduan Super Admin')
        ->assertSee('Lakukan pengawasan lintas koperasi')
        ->assertDontSee('Panduan Admin Primer')
        ->assertSee('+62 895 6049 5663 2')
        ->assertSee('zyohanes67@gmail.com')
        ->assertDontSee('Dokumen:');
});

test('custom tenant roles do not receive an admin primer guide', function () {
    $staff = staffUser();

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Panduan singkat');

    $this->get(route('panduan-singkat'))->assertForbidden();
    $this->get(route('panduan-singkat.cetak'))->assertForbidden();
});
