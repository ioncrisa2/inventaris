<?php

use App\Models\HariLibur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeNagerDateResponse(): array
{
    return [
        ['date' => '2026-01-01', 'localName' => 'Tahun Baru Masehi', 'name' => "New Year's Day"],
        ['date' => '2026-05-01', 'localName' => 'Hari Buruh Internasional', 'name' => 'International Labor Day'],
        ['date' => '2026-06-01', 'localName' => 'Hari Lahir Pancasila', 'name' => 'Pancasila Day'],
        ['date' => '2026-08-17', 'localName' => 'Hari Kemerdekaan Republik Indonesia', 'name' => 'Independence Day'],
        ['date' => '2026-12-25', 'localName' => 'Hari Raya Natal', 'name' => 'Christmas Day'],
    ];
}

beforeEach(function () {
    $this->actingAs(adminUser());

    // Sudah ada di database sebelum sinkronisasi (2 dari 5 tanggal API).
    HariLibur::create(['tanggal' => '2026-01-01', 'keterangan' => 'Tahun Baru Masehi']);
    HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'HUT RI']);
});

test('sinkronisasi shows the year picker form', function () {
    $this->get(route('hari-libur.sinkronisasi.create'))
        ->assertOk()
        ->assertSee('name="tahun"', false);
});

test('sinkronisasi compares api holidays against existing data without writing anything', function () {
    Http::fake([
        'date.nager.at/*' => Http::response(fakeNagerDateResponse(), 200),
    ]);

    $this->get(route('hari-libur.sinkronisasi.create', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Hari Buruh Internasional')
        ->assertSee('Hari Lahir Pancasila')
        ->assertSee('Hari Raya Natal')
        ->assertViewHas('hasil', function ($hasil) {
            return count($hasil['baru']) === 3 && count($hasil['sudahAda']) === 2;
        });

    // Membandingkan tidak menulis apa pun.
    expect(HariLibur::count())->toBe(2);
});

test('sinkronisasi terapkan hanya menyimpan tanggal yang dipilih dan tidak menyentuh data lama', function () {
    Http::fake([
        'date.nager.at/*' => Http::response(fakeNagerDateResponse(), 200),
    ]);

    $this->post(route('hari-libur.sinkronisasi.store'), [
        'tahun' => 2026,
        'pilihan' => ['2026-05-01', '2026-12-25'],
    ])->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));

    $this->assertDatabaseHas('hari_libur', [
        'tanggal' => '2026-05-01',
        'keterangan' => 'Hari Buruh Internasional',
    ]);
    $this->assertDatabaseHas('hari_libur', [
        'tanggal' => '2026-12-25',
        'keterangan' => 'Hari Raya Natal',
    ]);

    // Tanggal yang tidak dipilih (2026-06-01) tidak ikut ditambahkan.
    $this->assertDatabaseMissing('hari_libur', ['tanggal' => '2026-06-01']);

    // Data lama tidak berubah (keterangan manual "HUT RI" tetap, bukan tertimpa versi API).
    $this->assertDatabaseHas('hari_libur', [
        'tanggal' => '2026-08-17',
        'keterangan' => 'HUT RI',
    ]);

    expect(HariLibur::count())->toBe(4);
});

test('sinkronisasi menampilkan pesan error yang ramah saat API gagal', function () {
    Http::fake([
        'date.nager.at/*' => Http::response(null, 500),
    ]);

    $this->get(route('hari-libur.sinkronisasi.create', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Gagal mengambil data');

    expect(HariLibur::count())->toBe(2);
});

test('staff tanpa permission hari-libur.create tidak bisa mengakses sinkronisasi', function () {
    $this->actingAs(staffUser());

    $this->get(route('hari-libur.sinkronisasi.create'))->assertForbidden();

    $this->post(route('hari-libur.sinkronisasi.store'), [
        'tahun' => 2026,
        'pilihan' => ['2026-05-01'],
    ])->assertForbidden();
});
