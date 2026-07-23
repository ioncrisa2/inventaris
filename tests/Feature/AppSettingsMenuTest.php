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

test('app settings page offers sidebar and topbar layout options', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('pengaturan.edit'));

    $response->assertOk();
    $response->assertSee('Tampilan Aplikasi');
    $response->assertSee('value="sidebar"', false);
    $response->assertSee('value="topbar"', false);
});

test('app settings page offers system, light, and dark color modes', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('pengaturan.edit'));

    $response->assertOk();
    $response->assertSee('name="color-mode"', false);
    $response->assertSee('value="auto"', false);
    $response->assertSee('value="light"', false);
    $response->assertSee('value="dark"', false);
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
