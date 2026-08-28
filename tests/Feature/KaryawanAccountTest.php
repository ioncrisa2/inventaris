<?php

use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
