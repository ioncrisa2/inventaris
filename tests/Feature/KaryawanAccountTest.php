<?php

use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\KaryawanAccountService;
use App\Services\PlatformFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function accountTestKaryawan(UnitKerja $unitKerja, array $attributes = []): Karyawan
{
    return Karyawan::create(array_merge([
        'nik' => 'AKUN-001',
        'nama_lengkap' => 'Karyawan Akun',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_masuk_kerja' => '2020-01-01',
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 0,
    ], $attributes));
}

function configureEmployeeSelfServiceFeatures(array $enabledFeatures): void
{
    $owner = systemOwnerUser();
    $featureService = app(PlatformFeatureService::class);

    foreach (config('platform_features.employee_self_service_features') as $featureKey) {
        $featureService->setEnabled($featureKey, in_array($featureKey, $enabledFeatures, true), $owner);
    }

    Cache::forget(config('platform_features.cache_key'));
}

test('admin primer dapat menghubungkan dan melepas akun karyawan dalam koperasinya', function () {
    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Keuangan']);
    $karyawan = accountTestKaryawan($unit);
    $target = User::factory()->create([
        'koperasi_id' => $actor->koperasi_id,
        'unit_kerja_id' => null,
    ]);

    $this->put(route('karyawan.akun.update', $karyawan), [
        'user_id' => $target->id,
    ])->assertRedirect()->assertSessionHas('success');

    expect($karyawan->fresh()->user_id)->toBe($target->id)
        ->and($target->fresh()->unit_kerja_id)->toBe($unit->id);
    $this->assertDatabaseHas('karyawan_account_audit_logs', [
        'actor_user_id' => $actor->id,
        'karyawan_id' => $karyawan->id,
        'target_user_id' => $target->id,
        'action' => 'linked',
    ]);

    $this->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee($target->email);

    $this->delete(route('karyawan.akun.destroy', $karyawan))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($karyawan->fresh()->user_id)->toBeNull();
    $this->assertDatabaseHas('karyawan_account_audit_logs', [
        'actor_user_id' => $actor->id,
        'karyawan_id' => $karyawan->id,
        'target_user_id' => $target->id,
        'action' => 'unlinked',
    ]);
});

test('pengelolaan akun karyawan disembunyikan dan ditolak saat seluruh fitur personal nonaktif', function () {
    configureEmployeeSelfServiceFeatures([]);

    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Layanan Personal']);
    $linkedUser = User::factory()->create(['koperasi_id' => $actor->koperasi_id]);
    $availableUser = User::factory()->create(['koperasi_id' => $actor->koperasi_id]);
    $linkedKaryawan = accountTestKaryawan($unit, ['user_id' => $linkedUser->id]);
    $unlinkedKaryawan = accountTestKaryawan($unit, [
        'nik' => 'AKUN-002',
        'nama_lengkap' => 'Karyawan Belum Terhubung',
    ]);

    $this->get(route('karyawan.show', $linkedKaryawan))
        ->assertOk()
        ->assertDontSee('Akun Login Karyawan')
        ->assertDontSee($linkedUser->email);

    $this->put(route('karyawan.akun.update', $unlinkedKaryawan), ['user_id' => $availableUser->id])
        ->assertForbidden();
    $this->delete(route('karyawan.akun.destroy', $linkedKaryawan))
        ->assertForbidden();

    expect(fn () => app(KaryawanAccountService::class)->link($actor, $unlinkedKaryawan, $availableUser))
        ->toThrow(
            DomainException::class,
            'Pengelolaan akun karyawan tidak tersedia karena seluruh fitur personal sedang dinonaktifkan.',
        );

    expect($unlinkedKaryawan->fresh()->user_id)->toBeNull()
        ->and($linkedKaryawan->fresh()->user_id)->toBe($linkedUser->id);
});

test('pengelolaan akun karyawan tersedia saat salah satu fitur personal aktif', function (string $activeFeature) {
    configureEmployeeSelfServiceFeatures([$activeFeature]);

    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Layanan Personal']);
    $karyawan = accountTestKaryawan($unit);
    $target = User::factory()->create(['koperasi_id' => $actor->koperasi_id]);

    $this->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('Akun Login Karyawan')
        ->assertSee($target->email);

    $this->put(route('karyawan.akun.update', $karyawan), ['user_id' => $target->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($karyawan->fresh()->user_id)->toBe($target->id);
})->with([
    'Data Saya' => 'my_profile',
    'Absensi Saya' => 'my_attendance',
    'Slip Gaji Saya' => 'my_salary_slips',
]);

test('satu akun tidak dapat dihubungkan dengan dua karyawan', function () {
    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Operasional']);
    $target = User::factory()->create(['koperasi_id' => $actor->koperasi_id]);
    $pertama = accountTestKaryawan($unit);
    $kedua = accountTestKaryawan($unit, ['nik' => 'AKUN-002', 'nama_lengkap' => 'Karyawan Kedua']);

    $this->put(route('karyawan.akun.update', $pertama), ['user_id' => $target->id])
        ->assertSessionHasNoErrors();
    $this->put(route('karyawan.akun.update', $kedua), ['user_id' => $target->id])
        ->assertSessionHasErrors('user_id');

    expect($pertama->fresh()->user_id)->toBe($target->id)
        ->and($kedua->fresh()->user_id)->toBeNull();
});

test('akun karyawan tidak dapat diganti tanpa melepas hubungan lama', function () {
    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Legal']);
    $firstUser = User::factory()->create();
    $replacement = User::factory()->create();
    $karyawan = accountTestKaryawan($unit, ['user_id' => $firstUser->id]);

    $this->put(route('karyawan.akun.update', $karyawan), ['user_id' => $replacement->id])
        ->assertSessionHasErrors('user_id');

    expect($karyawan->fresh()->user_id)->toBe($firstUser->id);
});

test('akun dan karyawan dari koperasi berbeda tidak dapat dihubungkan', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer A']);
    $koperasiB = Koperasi::create(['nama' => 'Primer B']);
    $actor = adminPrimerUser($koperasiA);
    $this->actingAs($actor);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    $karyawan = accountTestKaryawan($unitA);
    $target = User::factory()->create();
    $target->koperasi_id = $koperasiB->id;
    $target->save();

    $this->put(route('karyawan.akun.update', $karyawan), ['user_id' => $target->id])
        ->assertSessionHasErrors('user_id');

    expect($karyawan->fresh()->user_id)->toBeNull();
});

test('akun platform tidak dapat dihubungkan dengan karyawan tenant', function () {
    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'SDM']);
    $karyawan = accountTestKaryawan($unit);
    $owner = systemOwnerUser();
    $this->actingAs($actor);

    $this->put(route('karyawan.akun.update', $karyawan), ['user_id' => $owner->id])
        ->assertSessionHasErrors('user_id');

    expect($karyawan->fresh()->user_id)->toBeNull();
});

test('pengguna tanpa permission tidak dapat mengelola akun karyawan', function () {
    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'Umum']);
    $karyawan = accountTestKaryawan($unit);
    $target = User::factory()->create(['koperasi_id' => $admin->koperasi_id]);
    $staff = staffUser(['koperasi_id' => $admin->koperasi_id]);

    $this->actingAs($staff)
        ->put(route('karyawan.akun.update', $karyawan), ['user_id' => $target->id])
        ->assertForbidden();

    expect($karyawan->fresh()->user_id)->toBeNull();
});

test('menghapus akun hanya melepas relasi dan mempertahankan data karyawan', function () {
    $actor = adminPrimerUser();
    $this->actingAs($actor);
    $unit = UnitKerja::create(['nama_unit' => 'Teknologi Informasi']);
    $target = User::factory()->create(['koperasi_id' => $actor->koperasi_id]);
    $karyawan = accountTestKaryawan($unit, ['user_id' => $target->id]);

    $target->delete();

    expect($karyawan->fresh())->not->toBeNull()
        ->and($karyawan->fresh()->user_id)->toBeNull();
});
