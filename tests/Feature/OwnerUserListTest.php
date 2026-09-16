<?php

use App\Models\Koperasi;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('system owner can view and filter the read-only tenant user list', function () {
    $owner = systemOwnerUser();
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Angkasa']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi Bumi']);
    $unit = UnitKerja::withoutGlobalScopes()->create([
        'koperasi_id' => $koperasiA->id,
        'nama_unit' => 'Operasional',
    ]);
    $userA = adminPrimerUser($koperasiA, [
        'name' => 'Alya Angkasa',
        'email' => 'alya@angkasa.test',
        'unit_kerja_id' => $unit->id,
    ]);
    $userB = adminPrimerUser($koperasiB, [
        'name' => 'Bima Bumi',
        'email' => 'bima@bumi.test',
    ]);

    $this->actingAs($owner)
        ->get(route('owner.userlist.index'))
        ->assertOk()
        ->assertSee('User List')
        ->assertSee($userA->email)
        ->assertSee('Operasional')
        ->assertSee($userB->email)
        ->assertViewHas('users', fn ($users) => ! $users->contains('id', $owner->id));

    $this->get(route('owner.userlist.index', ['koperasi_id' => $koperasiA->id]))
        ->assertOk()
        ->assertSee($userA->email)
        ->assertDontSee($userB->email);
});

test('non system owner cannot access the user list', function () {
    $this->actingAs(superAdminUser())
        ->get(route('owner.userlist.index'))
        ->assertForbidden();
});
