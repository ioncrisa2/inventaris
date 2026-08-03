<?php

use App\Models\HariLibur;
use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin navigates from year to cooperative before seeing holiday details', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alfa', 'is_active' => true]);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta', 'is_active' => true]);
    $koperasiKosong = Koperasi::create(['nama' => 'Primer Kosong', 'is_active' => false]);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($adminA);
    HariLibur::create(['tanggal' => '2026-01-01', 'keterangan' => 'Libur Alfa Satu']);
    HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'Libur Alfa Dua']);

    $this->actingAs($adminB);
    HariLibur::create(['tanggal' => '2026-05-01', 'keterangan' => 'Libur Beta']);

    $this->actingAs(superAdminUser())
        ->get(route('hari-libur.index'))
        ->assertOk()
        ->assertSee(route('hari-libur.tahun', ['tahun' => 2026]), false)
        ->assertDontSee('hari_libur_index_koperasi_id');

    $this->get(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Pilih koperasi primer untuk melihat daftar hari liburnya.')
        ->assertSee('Primer Alfa')
        ->assertSee('2 hari libur')
        ->assertSee('Primer Beta')
        ->assertSee('1 hari libur')
        ->assertSee('Primer Kosong')
        ->assertSee('0 hari libur')
        ->assertSee(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasiA]), false)
        ->assertDontSee('Libur Alfa Satu')
        ->assertDontSee('Libur Beta');

    $this->get(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasiA]))
        ->assertOk()
        ->assertSee('Daftar tanggal libur milik Primer Alfa.')
        ->assertSee('Libur Alfa Satu')
        ->assertSee('Libur Alfa Dua')
        ->assertDontSee('Libur Beta')
        ->assertSee(route('hari-libur.sinkronisasi.create', [
            'tahun' => 2026,
            'koperasi_id' => $koperasiA->id,
        ]));

    $this->get(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasiKosong]))
        ->assertOk()
        ->assertSee('Belum ada hari libur untuk Primer Kosong pada tahun 2026.');
});

test('admin primer cannot open the super admin cooperative holiday route', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);

    $this->actingAs(adminPrimerUser($koperasiA))
        ->get(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasiB]))
        ->assertForbidden();
});

test('legacy super admin cooperative filter redirects to the explicit nested detail route', function () {
    $koperasi = Koperasi::create(['nama' => 'Primer Lama']);

    $this->actingAs(superAdminUser())
        ->get(route('hari-libur.tahun', [
            'tahun' => 2026,
            'koperasi_id' => $koperasi->id,
        ]))
        ->assertRedirect(route('hari-libur.koperasi', [
            'tahun' => 2026,
            'koperasi' => $koperasi,
        ]));
});
