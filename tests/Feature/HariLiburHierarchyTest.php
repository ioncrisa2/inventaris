<?php

use App\Models\HariLibur;
use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function insertBaselineHolidayForHierarchy(string $tanggal, string $keterangan): int
{
    return DB::table('hari_libur')->insertGetId([
        'koperasi_id' => null,
        'cakupan_id' => 0,
        'tanggal' => $tanggal,
        'keterangan' => $keterangan,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('super admin sees one national baseline without choosing a primer', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);

    insertBaselineHolidayForHierarchy('2026-01-01', 'Tahun Baru Nasional');

    $this->actingAs(adminPrimerUser($koperasiA));
    HariLibur::create(['tanggal' => '2026-04-21', 'keterangan' => 'Libur Tambahan Alfa']);

    $this->actingAs(adminPrimerUser($koperasiB));
    HariLibur::create(['tanggal' => '2026-05-20', 'keterangan' => 'Libur Tambahan Beta']);

    $this->actingAs(superAdminUser())
        ->get(route('hari-libur.index'))
        ->assertOk()
        ->assertSee('Baseline hari libur nasional yang berlaku otomatis')
        ->assertSee('1 hari libur')
        ->assertSee(route('hari-libur.tahun', ['tahun' => 2026]), false);

    $this->get(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Baseline nasional yang otomatis berlaku')
        ->assertSee('Tahun Baru Nasional')
        ->assertSee('Baseline nasional')
        ->assertDontSee('Libur Tambahan Alfa')
        ->assertDontSee('Libur Tambahan Beta')
        ->assertDontSee('Primer Alfa')
        ->assertDontSee('Primer Beta');
});

test('primer sees global baseline and only its own additional holidays', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);
    $baselineId = insertBaselineHolidayForHierarchy('2026-01-01', 'Tahun Baru Nasional');

    $this->actingAs(adminPrimerUser($koperasiA));
    $tambahanA = HariLibur::create(['tanggal' => '2026-04-21', 'keterangan' => 'Libur Tambahan Alfa']);

    $this->actingAs(adminPrimerUser($koperasiB));
    HariLibur::create(['tanggal' => '2026-05-20', 'keterangan' => 'Libur Tambahan Beta']);

    $this->actingAs(adminPrimerUser($koperasiA))
        ->get(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertOk()
        ->assertSee('Gabungan baseline nasional dan hari libur tambahan milik Primer Alfa')
        ->assertSee('Tahun Baru Nasional')
        ->assertSee('Baseline nasional')
        ->assertSee('Libur Tambahan Alfa')
        ->assertSee('Tambahan primer')
        ->assertDontSee('Libur Tambahan Beta')
        ->assertDontSee(route('hari-libur.update', $baselineId), false)
        ->assertDontSee(route('hari-libur.destroy', $baselineId), false)
        ->assertSee(route('hari-libur.update', $tambahanA), false)
        ->assertSee(route('hari-libur.destroy', $tambahanA), false);
});

test('primer cannot add edit or delete a date from the national baseline', function () {
    $koperasi = Koperasi::create(['nama' => 'Primer Aman']);
    $baselineId = insertBaselineHolidayForHierarchy('2026-01-01', 'Tahun Baru Nasional');
    $admin = adminPrimerUser($koperasi);

    $this->actingAs($admin)
        ->post(route('hari-libur.store'), [
            'tanggal' => '2026-01-01',
            'keterangan' => 'Duplikat Primer',
        ])
        ->assertSessionHasErrors('tanggal');

    $this->put(route('hari-libur.update', $baselineId), [
        'tanggal' => '2026-01-02',
        'keterangan' => 'Mengubah Baseline',
    ])->assertForbidden();

    $this->delete(route('hari-libur.destroy', $baselineId))->assertForbidden();

    $this->assertDatabaseHas('hari_libur', [
        'id' => $baselineId,
        'koperasi_id' => null,
        'tanggal' => '2026-01-01',
        'keterangan' => 'Tahun Baru Nasional',
    ]);
});

test('legacy cooperative holiday routes redirect super admin to the national baseline', function () {
    $koperasi = Koperasi::create(['nama' => 'Primer Lama']);
    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasi]))
        ->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));

    $this->get(route('hari-libur.tahun', [
        'tahun' => 2026,
        'koperasi_id' => $koperasi->id,
    ]))->assertRedirect(route('hari-libur.tahun', ['tahun' => 2026]));
});

test('admin primer cannot open the legacy super admin cooperative holiday route', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);

    $this->actingAs(adminPrimerUser($koperasiA))
        ->get(route('hari-libur.koperasi', ['tahun' => 2026, 'koperasi' => $koperasiB]))
        ->assertForbidden();
});
