<?php

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Role;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function selfServiceKaryawan(UnitKerja $unit, User $user, array $attributes = []): Karyawan
{
    return Karyawan::create(array_merge([
        'user_id' => $user->id,
        'nik' => 'SELF-001',
        'nama_lengkap' => 'Karyawan Mandiri',
        'tanggal_lahir' => '1992-02-02',
        'nomor_ktp' => '1671010202920001',
        'unit_kerja_id' => $unit->id,
        'tanggal_masuk_kerja' => '2021-01-01',
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ], $attributes));
}

test('user tenant dapat melihat data dan absensinya sendiri tanpa permission operasional', function () {
    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'Layanan']);
    $user = staffUser(['koperasi_id' => $admin->koperasi_id]);
    $this->actingAs($admin);
    $karyawan = selfServiceKaryawan($unit, $user);
    Absensi::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => now()->startOfMonth()->toDateString(),
        'status' => 'Hadir',
    ]);

    $this->actingAs($user)
        ->get(route('me.profile'))
        ->assertOk()
        ->assertSee('Karyawan Mandiri')
        ->assertDontSee('1671010202920001');

    $this->get(route('me.attendance', ['bulan' => now()->month, 'tahun' => now()->year]))
        ->assertOk()
        ->assertSee('Karyawan Mandiri')
        ->assertSee('Hadir')
        ->assertDontSee('Simpan Absensi');
});

test('akun tanpa relasi karyawan mendapat empty state dan diarahkan dari halaman personal lain', function () {
    $user = staffUser();

    $this->actingAs($user)
        ->get(route('me.profile'))
        ->assertOk()
        ->assertSee('Akun belum terhubung dengan data karyawan');

    $this->get(route('me.attendance'))
        ->assertRedirect(route('me.profile'))
        ->assertSessionHas('error');
    $this->get(route('me.salary-slips.index'))
        ->assertRedirect(route('me.profile'))
        ->assertSessionHas('error');
});

test('portal slip hanya menampilkan slip published milik user login', function () {
    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'Keuangan']);
    $user = staffUser(['koperasi_id' => $admin->koperasi_id]);
    $otherUser = User::factory()->create();
    $this->actingAs($admin);
    $ownKaryawan = selfServiceKaryawan($unit, $user);
    $otherKaryawan = selfServiceKaryawan($unit, $otherUser, [
        'nik' => 'SELF-002',
        'nama_lengkap' => 'Karyawan Lain',
        'nomor_ktp' => '1671010202920002',
    ]);
    $published = TransaksiGaji::create([
        'karyawan_id' => $ownKaryawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
        'published_at' => now(),
        'published_by' => $admin->id,
    ]);
    $draft = TransaksiGaji::create([
        'karyawan_id' => $ownKaryawan->id,
        'bulan' => 8,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);
    $other = TransaksiGaji::create([
        'karyawan_id' => $otherKaryawan->id,
        'bulan' => 9,
        'tahun' => 2026,
        'gaji_pokok' => 6000000,
        'gaji_bersih' => 6000000,
        'published_at' => now(),
        'published_by' => $admin->id,
    ]);

    $this->actingAs($user)
        ->get(route('me.salary-slips.index'))
        ->assertOk()
        ->assertSee(route('me.salary-slips.show', $published), false)
        ->assertDontSee(route('me.salary-slips.show', $draft), false)
        ->assertDontSee(route('me.salary-slips.show', $other), false);

    $this->get(route('me.salary-slips.show', $published))
        ->assertOk()
        ->assertSeeInOrder(['Pendapatan', 'Gaji Pokok', 'Total Gaji', 'Potongan', 'Total Potongan', 'Take Home Pay'])
        ->assertSee('Rp 5.000.000');
    $this->assertDatabaseHas('salary_access_logs', [
        'actor_user_id' => $user->id,
        'transaksi_gaji_id' => $published->id,
        'action' => 'viewed',
    ]);

    $this->get(route('me.salary-slips.show', $draft))->assertNotFound();
    $this->get(route('me.salary-slips.show', $other))->assertNotFound();
});

test('admin primer dapat menerbitkan slip dan transaksi published tidak dapat diedit', function () {
    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'SDM']);
    $user = User::factory()->create();
    $karyawan = selfServiceKaryawan($unit, $user);
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);

    $this->patch(route('transaksi-gaji.publish', $transaksi))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaksi->fresh()->published_at)->not->toBeNull()
        ->and($transaksi->fresh()->published_by)->toBe($admin->id);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $user->id,
        'notifiable_type' => User::class,
    ]);

    $this->get(route('transaksi-gaji.edit', $transaksi))->assertForbidden();
});

test('role keuangan dapat memproses gaji orang lain tetapi tidak gajinya sendiri', function () {
    $admin = adminPrimerUser();
    $this->actingAs($admin);
    $unit = UnitKerja::create(['nama_unit' => 'Keuangan Mandiri']);
    $financeRole = new Role(['name' => 'Keuangan', 'guard_name' => 'web']);
    $financeRole->koperasi_id = $admin->koperasi_id;
    $financeRole->save();
    $financeRole->syncPermissions(['transaksi-gaji.create']);
    $financeUser = User::factory()->create();
    $financeUser->syncRoles([$financeRole]);
    $financeEmployee = selfServiceKaryawan($unit, $financeUser, [
        'nik' => 'FIN-001',
        'nama_lengkap' => 'Petugas Keuangan',
        'nomor_ktp' => '1671010202920011',
    ]);
    $otherEmployee = Karyawan::create([
        'nik' => 'FIN-002',
        'nama_lengkap' => 'Karyawan Penerima',
        'tanggal_lahir' => '1993-03-03',
        'unit_kerja_id' => $unit->id,
        'tanggal_masuk_kerja' => '2022-01-01',
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 4500000,
    ]);
    $component = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Tetap',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ]);
    $payload = fn (Karyawan $target, int $bulan) => [
        'karyawan_id' => $target->id,
        'bulan' => $bulan,
        'tahun' => 2026,
        'baris' => ["master_{$component->id}" => ['pakai' => '1']],
    ];

    $this->actingAs($financeUser)
        ->post(route('transaksi-gaji.store'), $payload($financeEmployee, 7))
        ->assertSessionHasErrors('karyawan_id');

    $this->post(route('transaksi-gaji.store'), $payload($otherEmployee, 8))
        ->assertRedirect();

    $this->assertDatabaseHas('transaksi_gaji', [
        'karyawan_id' => $otherEmployee->id,
        'bulan' => 8,
        'tahun' => 2026,
    ]);
});
