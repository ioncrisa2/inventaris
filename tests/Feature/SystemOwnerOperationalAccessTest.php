<?php

use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system owner cannot enter operational or control plane screens', function () {
    $owner = systemOwnerUser();

    foreach ([
        route('barang.index'),
        route('karyawan.index'),
        route('absensi.index'),
        route('transaksi-gaji.index'),
        route('laporan.inventaris'),
        route('pengguna.index'),
        route('role.index'),
        route('koperasi.index'),
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertForbidden();
    }
});

test('system owner cannot mutate tenant records through operational endpoints', function () {
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->post(route('barang.store'), [])
        ->assertForbidden();

    $this->post(route('karyawan.store'), [])
        ->assertForbidden();

    $this->post(route('transaksi-gaji.store'), [])
        ->assertForbidden();

    expect(Koperasi::query()->count())->toBe(0);
});

test('only owner reaches owner screens and dashboard redirect is actor aware', function () {
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('owner.dashboard'));

    $this->get(route('owner.dashboard'))->assertOk();
    $this->get(route('owner.system-health'))->assertOk();
    $this->get(route('owner.storage'))->assertOk();

    $this->actingAs(superAdminUser())
        ->get(route('owner.dashboard'))
        ->assertForbidden();

    $this->actingAs(adminPrimerUser())
        ->get(route('owner.dashboard'))
        ->assertForbidden();
});
