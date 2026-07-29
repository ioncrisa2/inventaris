<?php

use App\Models\HariLibur;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
});

test('index shows hari libur clustered per tahun', function () {
    HariLibur::create(['tanggal' => '2026-01-01', 'keterangan' => 'Tahun Baru Masehi']);
    HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);
    HariLibur::create(['tanggal' => '2027-01-01', 'keterangan' => 'Tahun Baru Masehi']);

    $this->get(route('hari-libur.index'))
        ->assertOk()
        ->assertSee('2026')
        ->assertSee('2 hari libur')
        ->assertSee('2027')
        ->assertSee('1 hari libur')
        ->assertSee(route('hari-libur.tahun', ['tahun' => 2026]), false);
});

test('tahun detail page lists every hari libur for that tahun', function () {
    HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);
    HariLibur::create(['tanggal' => '2027-01-01', 'keterangan' => 'Tahun Baru 2027']);

    $this->get(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Hari Libur 2026')
        ->assertSee('name="tanggal"', false)
        ->assertSee('data-keterangan="HUT RI"', false)
        ->assertDontSee('Tahun Baru 2027');
});

test('tahun detail page shows an empty state when the tahun has no data yet', function () {
    $this->get(route('hari-libur.tahun', ['tahun' => 2030]))
        ->assertOk()
        ->assertSee('Belum ada hari libur untuk tahun 2030.');
});

test('hari libur can be created and redirects to its tahun detail page', function () {
    $this->post(route('hari-libur.store'), [
        'tanggal' => '2026-08-17',
        'keterangan' => 'Hari Kemerdekaan Republik Indonesia',
    ])->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));

    $this->assertDatabaseHas('hari_libur', [
        'tanggal' => '2026-08-17',
        'keterangan' => 'Hari Kemerdekaan Republik Indonesia',
    ]);
});

test('hari libur can be edited and updated, redirecting to its tahun detail page', function () {
    $hariLibur = HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);

    $this->put(route('hari-libur.update', $hariLibur), [
        'tanggal' => '2026-08-17',
        'keterangan' => 'Hari Kemerdekaan Republik Indonesia',
    ])->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));

    $this->assertDatabaseHas('hari_libur', [
        'id' => $hariLibur->id,
        'keterangan' => 'Hari Kemerdekaan Republik Indonesia',
    ]);
});

test('editing a hari libur into a different tahun redirects to the new tahun', function () {
    $hariLibur = HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);

    $this->put(route('hari-libur.update', $hariLibur), [
        'tanggal' => '2027-08-17',
        'keterangan' => 'HUT RI',
    ])->assertRedirect(route('hari-libur.tahun', ['tahun' => 2027]));
});

test('hari libur tanggal must be unique except for its current record', function () {
    $hariLibur = HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);
    HariLibur::create(['tanggal' => '2026-12-25', 'keterangan' => 'Natal']);

    $this->put(route('hari-libur.update', $hariLibur), [
        'tanggal' => '2026-08-17',
        'keterangan' => 'HUT RI (diperbarui)',
    ])->assertSessionHasNoErrors();

    $this->from(route('hari-libur.tahun', ['tahun' => 2026]))
        ->put(route('hari-libur.update', $hariLibur), [
            'tanggal' => '2026-12-25',
            'keterangan' => 'HUT RI',
        ])
        ->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertSessionHasErrors('tanggal');
});

test('hari libur can be deleted and redirects to its tahun detail page', function () {
    $hariLibur = HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);

    $this->delete(route('hari-libur.destroy', $hariLibur))
        ->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));

    $this->assertDatabaseMissing('hari_libur', ['id' => $hariLibur->id]);
});

test('staff without hari-libur permission cannot manage hari libur', function () {
    $this->actingAs(staffUser());

    $this->post(route('hari-libur.store'), [
        'tanggal' => '2026-08-17',
        'keterangan' => 'HUT RI',
    ])->assertForbidden();
});
