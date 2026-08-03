<?php

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('super admin can update an admin primer account without changing its tenant or system role', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Amanah']);
    $adminPrimer = adminPrimerUser($koperasi, [
        'name' => 'Admin Primer Lama',
        'email' => 'primer.lama@example.com',
    ]);
    $adminPrimerRole = $adminPrimer->roles()->firstOrFail();

    $this->actingAs(superAdminUser());

    $this->get(route('pengguna.edit', $adminPrimer))
        ->assertOk()
        ->assertSee('Konteks akun: Koperasi Amanah')
        ->assertSee('Admin Primer — Koperasi Amanah');

    $this->put(route('pengguna.update', $adminPrimer), [
        'name' => 'Admin Primer Baru',
        'email' => 'primer.baru@example.com',
        'password' => 'Password-Baru-123',
        'role_id' => $adminPrimerRole->id,
        'unit_kerja_id' => null,
    ])->assertRedirect(route('pengguna.index'));

    $adminPrimer->refresh();

    expect($adminPrimer->name)->toBe('Admin Primer Baru')
        ->and($adminPrimer->email)->toBe('primer.baru@example.com')
        ->and($adminPrimer->koperasi_id)->toBe($koperasi->id)
        ->and($adminPrimer->isAdminPrimer())->toBeTrue()
        ->and(Hash::check('Password-Baru-123', $adminPrimer->password))->toBeTrue();
});

test('super admin user list has exact cooperative context and ignores foreign tenant filter for admin primer', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Alfa']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi Beta']);
    $adminA = adminPrimerUser($koperasiA, ['email' => 'admin.alfa@example.com']);
    $adminB = adminPrimerUser($koperasiB, ['email' => 'admin.beta@example.com']);

    $this->actingAs($adminA);
    UnitKerja::create(['nama_unit' => 'Unit Alfa']);
    $this->actingAs($adminB);
    UnitKerja::create(['nama_unit' => 'Unit Beta']);

    $this->actingAs(superAdminUser())
        ->get(route('pengguna.index', ['koperasi_id' => $koperasiA->id]))
        ->assertOk()
        ->assertSee('Kelola akun lintas koperasi dengan konteks tenant yang eksplisit.')
        ->assertSee('Koperasi Alfa')
        ->assertSee($adminA->email)
        ->assertDontSee($adminB->email);

    $this->get(route('pengguna.create', [
        'koperasi_id' => $koperasiA->id,
        'role_id' => $adminA->roles()->firstOrFail()->id,
    ]))
        ->assertOk()
        ->assertSee('Konteks akun baru: Koperasi Alfa')
        ->assertSee('Admin Primer — Koperasi Alfa')
        ->assertSee('Unit Alfa — Koperasi Alfa')
        ->assertDontSee('Admin Primer — Koperasi Beta')
        ->assertDontSee('Unit Beta — Koperasi Beta');

    $this->actingAs($adminA)
        ->get(route('pengguna.index', ['koperasi_id' => $koperasiB->id]))
        ->assertOk()
        ->assertSee($adminA->email)
        ->assertDontSee($adminB->email);
});

test('role and cooperative pages provide a clear path to manage admin primer accounts', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Terarah']);
    $adminPrimer = adminPrimerUser($koperasi);
    $adminPrimerRole = $adminPrimer->roles()->firstOrFail();

    $this->actingAs(superAdminUser());

    $this->get(route('role.index'))
        ->assertOk()
        ->assertSee('Kelola sistem')
        ->assertSee('Role Sistem')
        ->assertSee('Role sistem dilindungi.')
        ->assertSee(route('pengguna.index', [
            'role_id' => $adminPrimerRole->id,
            'koperasi_id' => $koperasi->id,
        ]));

    $this->get(route('koperasi.index'))
        ->assertOk()
        ->assertSee('Kelola Admin Primer Koperasi Terarah')
        ->assertSee(route('pengguna.index', [
            'koperasi_id' => $koperasi->id,
            'role_id' => $adminPrimerRole->id,
        ]));
});

test('last admin primer cannot be deleted until a replacement exists', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Terjaga']);
    $adminPrimer = adminPrimerUser($koperasi, ['email' => 'admin.utama@example.com']);
    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('pengguna.index', ['koperasi_id' => $koperasi->id]))
        ->assertOk()
        ->assertSee('Admin primer terakhir tidak dapat dihapus')
        ->assertDontSee('data-delete-url="'.route('pengguna.destroy', $adminPrimer).'"', false);

    $this->actingAs($superAdmin)
        ->from(route('pengguna.index'))
        ->delete(route('pengguna.destroy', $adminPrimer))
        ->assertRedirect(route('pengguna.index'))
        ->assertSessionHas('error', 'Akun ini adalah admin primer terakhir. Tambahkan admin primer pengganti sebelum mengubah role atau menghapus akun ini.');

    $this->assertDatabaseHas('users', ['id' => $adminPrimer->id]);

    adminPrimerUser($koperasi, ['email' => 'admin.pengganti@example.com']);

    $this->actingAs($superAdmin)
        ->get(route('pengguna.index', ['koperasi_id' => $koperasi->id]))
        ->assertOk()
        ->assertSee('data-delete-url="'.route('pengguna.destroy', $adminPrimer).'"', false);

    $this->actingAs($superAdmin)
        ->delete(route('pengguna.destroy', $adminPrimer))
        ->assertRedirect(route('pengguna.index'));

    $this->assertDatabaseMissing('users', ['id' => $adminPrimer->id]);
});

test('last admin primer cannot be reassigned to a non admin role', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Konsisten']);
    $adminPrimer = adminPrimerUser($koperasi);
    $staffRole = new Role(['name' => 'Operator', 'guard_name' => 'web']);
    $staffRole->koperasi_id = $koperasi->id;
    $staffRole->save();

    $this->actingAs(superAdminUser())
        ->put(route('pengguna.update', $adminPrimer), [
            'name' => $adminPrimer->name,
            'email' => $adminPrimer->email,
            'role_id' => $staffRole->id,
            'unit_kerja_id' => null,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Akun ini adalah admin primer terakhir. Tambahkan admin primer pengganti sebelum mengubah role atau menghapus akun ini.');

    $adminPrimer->refresh();

    expect($adminPrimer->koperasi_id)->toBe($koperasi->id)
        ->and($adminPrimer->isAdminPrimer())->toBeTrue()
        ->and($adminPrimer->hasRole($staffRole))->toBeFalse();
});
