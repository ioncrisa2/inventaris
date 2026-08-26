<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user dropdown links to the dedicated app settings page', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Pengaturan Aplikasi');
    $response->assertSee('href="'.route('pengaturan.edit').'"', false);
    $response->assertDontSee('id="appSettingsModal"', false);
});

test('app header offers sidebar and topbar layout options in user menu', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('data-app-layout-option="sidebar"', false);
    $response->assertSee('data-app-layout-option="topbar"', false);
});

test('app header offers system, light, and dark color modes in user menu', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('data-color-mode-option="auto"', false);
    $response->assertSee('data-color-mode-option="light"', false);
    $response->assertSee('data-color-mode-option="dark"', false);
});

test('topbar navigation mirrors the same permission-gated menu as the sidebar', function () {
    $this->actingAs(staffUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    // Staff role tidak punya izin pengguna.view/role.view, jadi grup
    // Administrasi tidak boleh muncul di topbar-nav maupun sidebar-nav.
    $response->assertDontSee('Manajemen Pengguna');
    $response->assertDontSee('Role &amp; Hak Akses');
    // Tapi Staff tetap bisa lihat Laporan (topbar-nav pakai dropdown).
    $response->assertSee('Laporan Penggajian');
});
