<?php

use App\Models\Karyawan;
use App\Models\PlatformFeatureAuditLog;
use App\Models\ProductRequest;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system owner dapat menonaktifkan fitur tanpa menghapus data yang tersimpan', function () {
    $admin = adminPrimerUser();
    $productRequest = productRequestFor($admin);
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'product_requests'), ['enabled' => false])
        ->assertRedirect()
        ->assertSessionHas('success', 'Akses Request Produk berhasil dinonaktifkan secara global.');

    $this->assertDatabaseHas('platform_feature_settings', [
        'feature_key' => 'product_requests',
        'enabled' => false,
        'updated_by' => $owner->id,
    ]);
    $this->assertDatabaseHas('platform_feature_audit_logs', [
        'feature_key' => 'product_requests',
        'actor_user_id' => $owner->id,
        'action' => 'disabled',
    ]);
    expect(ProductRequest::query()->withoutGlobalScopes()->find($productRequest->id))->not->toBeNull();
});

test('fitur nonaktif hilang dari navigasi dan url langsung tertutup untuk admin primer', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'product_requests'), ['enabled' => false])
        ->assertRedirect();

    $admin = adminPrimerUser();
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Request Produk');

    $this->get(route('product-requests.index'))->assertNotFound();
    $this->post(route('product-requests.store'), [])->assertNotFound();
});

test('fitur nonaktif juga menutup akses langsung super admin', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'cooperatives'), ['enabled' => false])
        ->assertRedirect();

    $superAdmin = superAdminUser();
    $this->actingAs($superAdmin)
        ->get(route('koperasi.index'))
        ->assertNotFound();
});

test('system owner tetap memiliki panel pemulihan dan akses inbox produk', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'product_requests'), ['enabled' => false])
        ->assertRedirect();

    $this->get(route('owner.features.index'))
        ->assertOk()
        ->assertSee('Request Produk')
        ->assertSee('Nonaktif')
        ->assertSee('Aktifkan kembali');

    $this->get(route('owner.product-requests.index'))->assertOk();
});

test('fitur dapat diaktifkan kembali dan audit mencatat kedua transisi', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'product_requests'), ['enabled' => false])
        ->assertRedirect();
    $this->patch(route('owner.features.update', 'product_requests'), ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('success', 'Akses Request Produk berhasil diaktifkan kembali.');

    $admin = adminPrimerUser();
    $this->actingAs($admin)->get(route('product-requests.index'))->assertOk();

    $this->assertDatabaseHas('platform_feature_settings', [
        'feature_key' => 'product_requests',
        'enabled' => true,
    ]);
    expect(
        PlatformFeatureAuditLog::query()
            ->where('feature_key', 'product_requests')
            ->pluck('action')
            ->all(),
    )->toBe(['disabled', 'enabled']);
});

test('panel akses fitur hanya dapat dibuka system owner', function () {
    $admin = adminPrimerUser();

    $this->actingAs($admin)->get(route('owner.features.index'))->assertForbidden();
    $this->patch(route('owner.features.update', 'not_registered'), ['enabled' => false])->assertForbidden();
});

test('pengaturan aplikasi yang nonaktif juga hilang dari dropdown pengguna', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'app_settings'), ['enabled' => false])
        ->assertRedirect();

    $admin = adminPrimerUser();
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Pengaturan Aplikasi');

    $this->get(route('pengaturan.edit'))->assertNotFound();
});

test('fitur slip gaji pribadi mengendalikan tombol dan endpoint penerbitan slip', function () {
    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->patch(route('owner.features.update', 'my_salary_slips'), ['enabled' => false])
        ->assertRedirect();

    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'Keuangan']);
    $karyawan = Karyawan::create([
        'nik' => 'SLIP-001',
        'nama_lengkap' => 'Penerima Slip',
        'tanggal_lahir' => '1992-02-02',
        'nomor_ktp' => '1671010202920001',
        'unit_kerja_id' => $unit->id,
        'tanggal_masuk_kerja' => '2021-01-01',
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => 8,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);

    $this->get(route('transaksi-gaji.show', $transaksi))
        ->assertOk()
        ->assertDontSee('Terbitkan Slip');

    $this->patch(route('transaksi-gaji.publish', $transaksi))->assertNotFound();

    expect($transaksi->fresh()->published_at)->toBeNull();
});
