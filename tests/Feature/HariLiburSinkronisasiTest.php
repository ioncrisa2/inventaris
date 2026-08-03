<?php

use App\Models\HariLibur;
use App\Models\Koperasi;
use App\Services\HariLiburSinkronisasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeTanggalMerahResponse(?array $data = null): array
{
    $data ??= [
        ['date' => '2026-01-01', 'day' => 'Kamis', 'name' => 'Tahun Baru 2026 Masehi', 'type' => 'holiday'],
        ['date' => '2026-02-16', 'day' => 'Senin', 'name' => 'Cuti Bersama Tahun Baru Imlek', 'type' => 'leave'],
        ['date' => '2026-08-17', 'day' => 'Senin', 'name' => 'Proklamasi Kemerdekaan', 'type' => 'holiday'],
    ];

    $holidays = collect($data)->where('type', 'holiday')->count();
    $leave = collect($data)->where('type', 'leave')->count();

    return [
        'success' => true,
        'data' => $data,
        'meta' => [
            'total' => count($data),
            'total_holidays' => $holidays,
            'total_leave' => $leave,
            'year' => 2026,
        ],
    ];
}

function holidaySnapshot(int $koperasiId, int $tahun = 2026): string
{
    return app(HariLiburSinkronisasiService::class)->bandingkan($koperasiId, $tahun)['snapshot'];
}

function fakeTanggalMerahApi(?array $payload = null, int $status = 200): void
{
    Http::fake([
        'tanggalmerah.upset.dev/*' => Http::response($payload ?? fakeTanggalMerahResponse(), $status),
    ]);
}

test('only super admin sees and can open the holiday synchronization control plane', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aman']);
    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('hari-libur.index'))
        ->assertOk()
        ->assertSee(route('hari-libur.sinkronisasi.create'), false)
        ->assertSee('Sinkronkan API');

    $this->get(route('hari-libur.sinkronisasi.create'))
        ->assertOk()
        ->assertSee('Koperasi Aman')
        ->assertSee('Periksa tanggal sebelum menerapkan.')
        ->assertSee('name="tahun"', false)
        ->assertSee('name="koperasi_id"', false);

    Http::assertNothingSent();
});

test('admin primer cannot access synchronization even though it can create holidays', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Primer']);
    $adminPrimer = adminPrimerUser($koperasi);

    expect($adminPrimer->can('hari-libur.create'))->toBeTrue();

    $this->actingAs($adminPrimer)
        ->get(route('hari-libur.sinkronisasi.create'))
        ->assertForbidden();

    $this->post(route('hari-libur.sinkronisasi.store'), [
        'tahun' => 2026,
        'koperasi_id' => $koperasi->id,
        'snapshot' => str_repeat('a', 64),
        'pilihan' => ['2026-01-01'],
    ])->assertForbidden();
});

test('preview compares only against the explicitly selected cooperative without writing', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi Beta']);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($adminB);
    HariLibur::create(['tanggal' => '2026-01-01', 'keterangan' => 'Data milik Beta']);

    fakeTanggalMerahApi();

    $this->actingAs(superAdminUser())
        ->get(route('hari-libur.sinkronisasi.create', [
            'tahun' => 2026,
            'koperasi_id' => $koperasiA->id,
        ]))
        ->assertOk()
        ->assertViewHas('hasil', fn (array $hasil) => count($hasil['baru']) === 3 && count($hasil['sudahAda']) === 0)
        ->assertSee('Koperasi Alfa');

    $this->assertDatabaseCount('hari_libur', 1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://tanggalmerah.upset.dev/api/holidays?year=2026'
        && (int) $request['year'] === 2026);
});

test('apply inserts only API dates selected for the target cooperative and preserves manual data', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi Beta']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($adminA);
    HariLibur::create(['tanggal' => '2026-08-17', 'keterangan' => 'Keterangan manual Alfa']);
    $this->actingAs($adminB);
    HariLibur::create(['tanggal' => '2026-01-01', 'keterangan' => 'Data Beta']);

    fakeTanggalMerahApi();
    $snapshot = holidaySnapshot($koperasiA->id);

    $this->actingAs(superAdminUser())
        ->post(route('hari-libur.sinkronisasi.store'), [
            'tahun' => 2026,
            'koperasi_id' => $koperasiA->id,
            'snapshot' => $snapshot,
            'pilihan' => ['2026-01-01', '2026-08-17', '2026-12-31'],
        ])
        ->assertRedirect(route('hari-libur.koperasi', [
            'tahun' => 2026,
            'koperasi' => $koperasiA,
        ]))
        ->assertSessionHas('success', '1 hari libur berhasil ditambahkan dari API.');

    $this->assertDatabaseHas('hari_libur', [
        'koperasi_id' => $koperasiA->id,
        'tanggal' => '2026-01-01',
        'keterangan' => 'Tahun Baru 2026 Masehi',
    ]);
    $this->assertDatabaseHas('hari_libur', [
        'koperasi_id' => $koperasiA->id,
        'tanggal' => '2026-08-17',
        'keterangan' => 'Keterangan manual Alfa',
    ]);
    $this->assertDatabaseMissing('hari_libur', [
        'koperasi_id' => $koperasiA->id,
        'tanggal' => '2026-12-31',
    ]);
    $this->assertDatabaseHas('hari_libur', [
        'koperasi_id' => $koperasiB->id,
        'tanggal' => '2026-01-01',
        'keterangan' => 'Data Beta',
    ]);
});

test('synchronization is idempotent for the same cooperative and date', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Idempoten']);
    $superAdmin = superAdminUser();
    fakeTanggalMerahApi();
    $snapshot = holidaySnapshot($koperasi->id);
    $payload = [
        'tahun' => 2026,
        'koperasi_id' => $koperasi->id,
        'snapshot' => $snapshot,
        'pilihan' => ['2026-02-16'],
    ];

    $this->actingAs($superAdmin)->post(route('hari-libur.sinkronisasi.store'), $payload)
        ->assertSessionHas('success', '1 hari libur berhasil ditambahkan dari API.');
    $this->post(route('hari-libur.sinkronisasi.store'), $payload)
        ->assertSessionHas('success', 'Tidak ada hari libur baru yang ditambahkan.');

    $this->assertDatabaseCount('hari_libur', 1);
});

test('HTTP failure aborts synchronization without partial writes', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aman']);
    fakeTanggalMerahApi(['success' => false], 500);

    $this->actingAs(superAdminUser())
        ->post(route('hari-libur.sinkronisasi.store'), [
            'tahun' => 2026,
            'koperasi_id' => $koperasi->id,
            'snapshot' => str_repeat('a', 64),
            'pilihan' => ['2026-01-01'],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Data hari libur untuk tahun tersebut tidak tersedia dari layanan publik.');

    $this->assertDatabaseCount('hari_libur', 0);
});

test('invalid API payload aborts synchronization without partial writes', function (array $payload) {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aman']);
    fakeTanggalMerahApi($payload);

    $this->actingAs(superAdminUser())
        ->post(route('hari-libur.sinkronisasi.store'), [
            'tahun' => 2026,
            'koperasi_id' => $koperasi->id,
            'snapshot' => str_repeat('a', 64),
            'pilihan' => ['2026-01-01'],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Respons layanan hari libur tidak valid. Sinkronisasi dibatalkan agar data aplikasi tetap aman.');

    $this->assertDatabaseCount('hari_libur', 0);
})->with([
    'wrong response year' => function () {
        $payload = fakeTanggalMerahResponse();
        $payload['meta']['year'] = 2025;

        return $payload;
    },
    'duplicate dates' => function () {
        $data = fakeTanggalMerahResponse()['data'];
        $data[] = $data[0];

        return fakeTanggalMerahResponse($data);
    },
    'unknown holiday type' => function () {
        $data = fakeTanggalMerahResponse()['data'];
        $data[0]['type'] = 'optional';

        return fakeTanggalMerahResponse($data);
    },
]);

test('apply is rejected when API data changed after the preview', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Konsisten']);
    $responsAwal = fakeTanggalMerahResponse();
    $dataBerubah = $responsAwal['data'];
    $dataBerubah[0]['name'] = 'Nama berubah setelah pratinjau';
    $responsBerubah = fakeTanggalMerahResponse($dataBerubah);

    Http::fake([
        'tanggalmerah.upset.dev/*' => Http::sequence()
            ->push($responsAwal)
            ->push($responsBerubah),
    ]);

    $snapshot = holidaySnapshot($koperasi->id);

    $this->actingAs(superAdminUser())
        ->post(route('hari-libur.sinkronisasi.store'), [
            'tahun' => 2026,
            'koperasi_id' => $koperasi->id,
            'snapshot' => $snapshot,
            'pilihan' => ['2026-01-01'],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Data API berubah sejak pratinjau ditampilkan. Muat ulang pratinjau dan periksa kembali sebelum menerapkan.');

    $this->assertDatabaseCount('hari_libur', 0);
});
