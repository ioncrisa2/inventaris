<?php

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\DemoStaffRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Buat user dengan role super_admin (semua permission, lintas koperasi)
 * untuk test yang butuh akses penuh. Menjalankan PermissionSeeder supaya
 * role & permission-nya tersedia di database in-memory milik test yang
 * bersangkutan.
 */
function adminUser(array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    return tap(User::factory()->create($attributes))->assignRole('super_admin');
}

/**
 * Buat user dengan role Staff (akses terbatas, lihat DemoStaffRoleSeeder
 * untuk daftar permission-nya) untuk test yang memverifikasi pembatasan
 * akses. Staff BUKAN role sistem — di dunia nyata role seperti ini dibuat
 * manual oleh super_admin per koperasi, di sini cukup di-seed langsung.
 */
function staffUser(array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);
    test()->seed(DemoStaffRoleSeeder::class);

    return tap(User::factory()->create($attributes))->assignRole('Staff');
}

/**
 * Buat user dengan role admin_primer, terikat ke satu koperasi (baru
 * dibuat kecuali diberikan lewat $koperasi). Dipakai test yang perlu
 * memverifikasi perilaku ter-scope per-tenant sungguhan (lihat
 * KoperasiScope/BelongsToKoperasi) — beda dari adminUser()/staffUser()
 * yang koperasi_id-nya tetap null.
 */
function adminPrimerUser(?Koperasi $koperasi = null, array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    $koperasi ??= Koperasi::create(['nama' => 'Koperasi Test '.uniqid()]);

    $role = new Role(['name' => 'admin_primer', 'guard_name' => 'web']);
    $role->koperasi_id = $koperasi->id;
    $role->save();
    $role->syncPermissions(PermissionCatalog::adminPrimerTemplate());

    $user = User::factory()->create($attributes);
    $user->koperasi_id = $koperasi->id;
    $user->save();
    $user->assignRole($role);

    return $user;
}
