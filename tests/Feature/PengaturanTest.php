<?php

use App\Models\Barang;
use App\Models\Koperasi;
use App\Models\UnitKerja;
use App\Services\IdentitasAplikasiService;
use App\Services\KodeBarangGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('admin can view and update hari operasional', function () {
    $this->actingAs(adminPrimerUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertSee('Hari Operasional');

    $this->put(route('pengaturan.hari-operasional.update'), [
        'hari_operasional' => [1, 2, 3, 4, 5],
    ])->assertRedirect(route('pengaturan.edit'));

    $this->assertDatabaseHas('pengaturan', [
        'key' => 'hari_operasional',
        'value' => '[1,2,3,4,5]',
    ]);
});

test('hari operasional update requires at least one day', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.hari-operasional.update'), [
        'hari_operasional' => [],
    ])->assertSessionHasErrors('hari_operasional');
});

test('staff cannot update hari operasional', function () {
    $this->actingAs(staffUser());

    $this->put(route('pengaturan.hari-operasional.update'), [
        'hari_operasional' => [1, 2, 3, 4, 5],
    ])->assertForbidden();
});

test('admin can view and update pengaturan', function () {
    $this->actingAs(adminPrimerUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertSee('Format Penomoran Inventaris')
        ->assertSee('Jumlah digit nomor urut');

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => '{KATEGORI}.{UNIT}.{TAHUN}.{URUT}',
        'digit_nomor_urut' => 5,
    ])->assertRedirect(route('pengaturan.edit'));

    $this->assertDatabaseHas('pengaturan', [
        'key' => 'format_kode_barang',
        'value' => '{KATEGORI}.{UNIT}.{TAHUN}.{URUT}',
    ]);

    $this->assertDatabaseHas('pengaturan', [
        'key' => 'digit_nomor_urut',
        'value' => '5',
    ]);
});

test('staff cannot manage inventory numbering', function () {
    $this->actingAs(staffUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertDontSee('Format Penomoran Inventaris');

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{URUT}',
        'digit_nomor_urut' => 4,
    ])->assertForbidden();
});

test('pengaturan rejects unknown tokens', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{TIDAK_DIKENAL}-{URUT}',
        'digit_nomor_urut' => 4,
    ])->assertSessionHasErrors('format_kode_barang');
});

test('pengaturan requires the sequence token to preserve unique codes', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{TAHUN}',
        'digit_nomor_urut' => 4,
    ])->assertSessionHasErrors('format_kode_barang');
});

test('kode barang generator produces the expected pattern from the default template', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'Teknologi Informasi', 'kode' => 'IT']);

    $kode = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    expect($kode)->toBe('IT-KL1-2026-0001');
});

test('kode barang generator falls back to a derived unit code when kode is blank', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'Sumber Daya Manusia']);

    $kode = $generator->generate('Bukan Bangunan - Kelompok 2', $unitKerja->id, '2026-07-21');

    expect($kode)->toBe('SUM-KL2-2026-0001');
});

test('kode barang generator increments sequence and avoids collisions', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);

    $kode1 = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    Barang::create([
        'kode_barang' => $kode1,
        'nama_barang' => 'Laptop',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_perolehan' => '2026-07-21',
        'harga_perolehan' => 1000000,
    ]);

    $kode2 = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    expect($kode2)->not->toBe($kode1);
});

test('identitas koperasi falls back to app name when not configured', function () {
    expect(app(IdentitasAplikasiService::class)->nama())->toBe(config('app.name'))
        ->and(app(IdentitasAplikasiService::class)->alamat())->toBeNull()
        ->and(app(IdentitasAplikasiService::class)->logoUrl())->toBeNull();
});

test('admin can update identitas koperasi including a logo', function () {
    Storage::fake('public');
    $this->actingAs(adminPrimerUser());
    $logo = UploadedFile::fake()->image('logo.png');

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertSee('Identitas Koperasi');

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Sejahtera Bersama',
        'alamat' => 'Jl. Merdeka No. 1, Jakarta',
        'logo' => $logo,
    ])->assertRedirect(route('pengaturan.edit'));

    $service = app(IdentitasAplikasiService::class);

    expect($service->nama())->toBe('Koperasi Sejahtera Bersama')
        ->and($service->alamat())->toBe('Jl. Merdeka No. 1, Jakarta')
        ->and($service->logoPath())->not->toBeNull();
    Storage::disk('public')->assertExists($service->logoPath());
});

test('identitas logo is replaced and the old file is deleted', function () {
    Storage::fake('public');
    $this->actingAs(adminPrimerUser());

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Awal',
        'logo' => UploadedFile::fake()->image('lama.png'),
    ]);

    $pathLama = app(IdentitasAplikasiService::class)->logoPath();
    Storage::disk('public')->assertExists($pathLama);

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Awal',
        'logo' => UploadedFile::fake()->image('baru.png'),
    ]);

    $pathBaru = app(IdentitasAplikasiService::class)->logoPath();

    expect($pathBaru)->not->toBe($pathLama);
    Storage::disk('public')->assertMissing($pathLama);
    Storage::disk('public')->assertExists($pathBaru);
});

test('identitas nama and alamat are preserved when update is submitted without a new logo', function () {
    Storage::fake('public');
    $this->actingAs(adminPrimerUser());

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Awal',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);
    $pathAwal = app(IdentitasAplikasiService::class)->logoPath();

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Diperbarui',
    ])->assertRedirect(route('pengaturan.edit'));

    $service = app(IdentitasAplikasiService::class);
    expect($service->nama())->toBe('Koperasi Diperbarui')
        ->and($service->logoPath())->toBe($pathAwal);
    Storage::disk('public')->assertExists($pathAwal);
});

test('identitas update rejects a non-image logo and requires nama', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Sejahtera',
        'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('logo');

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => '',
    ])->assertSessionHasErrors('nama');
});

test('staff without pengaturan.view cannot see or update identitas koperasi', function () {
    $this->actingAs(staffUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertDontSee('Identitas Koperasi');

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Percobaan',
    ])->assertForbidden();
});

test('kode barang generator respects the configured sequence digit count', function () {
    $this->actingAs(adminPrimerUser());

    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);

    $generator->simpanPengaturan('INV-{URUT}', 6);

    expect($generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21'))
        ->toBe('INV-000001');
});

test('super_admin sees the default app identity instead of any koperasi\'s branding', function () {
    $this->actingAs(adminPrimerUser());
    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Rahasia',
        'alamat' => 'Jl. Tenant, Kota Tenant',
    ]);

    $this->actingAs(superAdminUser());

    expect(app(IdentitasAplikasiService::class)->nama())->toBe(config('app.name'))
        ->and(app(IdentitasAplikasiService::class)->alamat())->toBeNull()
        ->and(app(IdentitasAplikasiService::class)->logoUrl())->toBeNull();

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertDontSee('Koperasi Rahasia');
});

test('super_admin cannot save identitas koperasi, kode barang, or hari operasional', function () {
    $this->actingAs(adminPrimerUser());
    $this->put(route('pengaturan.identitas.update'), ['nama' => 'Koperasi Awal']);

    $this->actingAs(superAdminUser());

    $this->put(route('pengaturan.identitas.update'), [
        'nama' => 'Koperasi Dibajak Super Admin',
    ])->assertForbidden();
    expect(app(IdentitasAplikasiService::class)->nama())->toBe(config('app.name'));

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'SA-{URUT}',
        'digit_nomor_urut' => 4,
    ])->assertForbidden();

    $this->put(route('pengaturan.hari-operasional.update'), [
        'hari_operasional' => [1, 2, 3],
    ])->assertForbidden();
});

test('super_admin sees read-only settings page, not editable forms', function () {
    $this->actingAs(superAdminUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertDontSee('id="identitasForm"', false)
        ->assertDontSee('id="inventoryNumberingForm"', false)
        ->assertDontSee('id="hariOperasionalForm"', false)
        ->assertSee('dikelola oleh admin_primer masing-masing koperasi');
});

test('login page always shows the default app identity, never a tenant\'s branding', function () {
    $this->actingAs(adminPrimerUser());
    $this->put(route('pengaturan.identitas.update'), ['nama' => 'Koperasi Terlihat Di Login']);
    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertDontSee('Koperasi Terlihat Di Login');
});

test('tenant user dynamically sees their own koperasi name without manual configuration', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Mandiri Sejahtera']);
    $admin = adminPrimerUser($koperasi);

    $this->actingAs($admin);

    expect(app(IdentitasAplikasiService::class)->nama())->toBe('Koperasi Mandiri Sejahtera');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Koperasi Mandiri Sejahtera');
});

test('super_admin dynamically sees default Puskopdit PHS app name', function () {
    $this->actingAs(superAdminUser());

    expect(app(IdentitasAplikasiService::class)->nama())->toBe('Puskopdit PHS');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Puskopdit PHS');
});
