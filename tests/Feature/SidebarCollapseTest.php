<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sidebar renders unique collapsible group ids for desktop and mobile nav', function () {
    $this->actingAs(adminUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('id="desktop-group-sdm-kehadiran"', false);
    $response->assertSee('id="mobile-group-sdm-kehadiran"', false);
    $response->assertSee('id="desktop-group-penggajian"', false);
    $response->assertSee('id="mobile-group-penggajian"', false);
    $response->assertSee('id="desktop-group-manajemen-aset"', false);
    $response->assertSee('id="mobile-group-manajemen-aset"', false);
    $response->assertSee('data-bs-target="#desktop-group-administrasi"', false);
    $response->assertSee('data-bs-target="#mobile-group-administrasi"', false);
});

test('topbar renders the desktop sidebar icon-only toggle button', function () {
    $this->actingAs(adminUser());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="sidebarToggle"', false);
});
