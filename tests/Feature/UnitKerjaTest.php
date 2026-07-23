<?php

use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
});

test('unit kerja can be created', function () {
    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertSee('name="nama_unit"', false);

    $this->post(route('unit-kerja.store'), [
        'nama_unit' => 'Teknologi Informasi',
    ])->assertRedirect(route('unit-kerja.index'));

    $this->assertDatabaseHas('unit_kerja', [
        'nama_unit' => 'Teknologi Informasi',
    ]);
});

test('unit kerja can be edited and updated', function () {
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);

    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertSee('data-nama-unit="IT"', false);

    $this->put(route('unit-kerja.update', $unitKerja), [
        'nama_unit' => 'Teknologi Informasi',
    ])->assertRedirect(route('unit-kerja.index'));

    $this->assertDatabaseHas('unit_kerja', [
        'id' => $unitKerja->id,
        'nama_unit' => 'Teknologi Informasi',
    ]);
});

test('unit kerja kode can be set and updated', function () {
    $this->post(route('unit-kerja.store'), [
        'nama_unit' => 'Teknologi Informasi',
        'kode' => 'IT',
    ])->assertRedirect(route('unit-kerja.index'));

    $unitKerja = UnitKerja::where('nama_unit', 'Teknologi Informasi')->firstOrFail();
    expect($unitKerja->kode)->toBe('IT');

    $this->put(route('unit-kerja.update', $unitKerja), [
        'nama_unit' => 'Teknologi Informasi',
        'kode' => 'TI',
    ])->assertRedirect(route('unit-kerja.index'));

    expect($unitKerja->fresh()->kode)->toBe('TI');
});

test('unit kerja name must be unique except for its current record', function () {
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
    UnitKerja::create(['nama_unit' => 'Keuangan']);

    $this->put(route('unit-kerja.update', $unitKerja), [
        'nama_unit' => 'IT',
    ])->assertSessionHasNoErrors();

    $this->from(route('unit-kerja.index'))
        ->put(route('unit-kerja.update', $unitKerja), [
            'nama_unit' => 'Keuangan',
        ])
        ->assertRedirect(route('unit-kerja.index'))
        ->assertSessionHasErrors('nama_unit');
});
