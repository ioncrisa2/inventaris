<?php

use App\Models\Koperasi;
use App\Models\ProductRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductRequestService;
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

/** Buat admin operasional tenant untuk mayoritas feature test. */
function adminUser(array $attributes = []): User
{
    return adminPrimerUser(null, $attributes);
}

/** Buat super admin global untuk test control-plane/lintas tenant. */
function superAdminUser(array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    $role = Role::query()
        ->where('name', 'super_admin')
        ->where('guard_name', 'web')
        ->whereNull('koperasi_id')
        ->firstOrFail();

    $user = User::factory()->create($attributes);
    $user->koperasi_id = null;
    $user->save();
    $user->assignRole($role);

    return $user;
}

/** Buat system owner global tanpa permission tenant untuk feature test. */
function systemOwnerUser(array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    $role = Role::query()
        ->where('name', 'system_owner')
        ->where('guard_name', 'web')
        ->whereNull('koperasi_id')
        ->firstOrFail();

    $user = User::factory()->create($attributes);
    $user->koperasi_id = null;
    $user->unit_kerja_id = null;
    $user->save();
    $user->syncRoles([$role]);

    return $user;
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

    $koperasiId = $attributes['koperasi_id']
        ?? auth()->user()?->koperasi_id
        ?? Koperasi::create(['nama' => 'Koperasi Staff Test '.uniqid()])->id;

    $user = User::factory()->create($attributes);
    $user->koperasi_id = $koperasiId;
    $user->save();

    $previousUser = auth()->user();
    auth()->setUser($user);
    test()->seed(DemoStaffRoleSeeder::class);
    if ($previousUser) {
        auth()->setUser($previousUser);
    } else {
        auth()->logout();
    }

    $role = Role::query()
        ->where('name', 'Staff')
        ->where('guard_name', 'web')
        ->where('koperasi_id', $koperasiId)
        ->firstOrFail();
    $user->assignRole($role);

    return $user;
}

/**
 * Buat user dengan role admin_primer, terikat ke satu koperasi (baru
 * dibuat kecuali diberikan lewat $koperasi). Dipakai test yang perlu
 * memverifikasi perilaku ter-scope per-tenant sungguhan (lihat
 * KoperasiScope/BelongsToKoperasi). adminUser() adalah alias praktis untuk
 * helper ini; staffUser() juga selalu memiliki koperasi.
 */
function adminPrimerUser(?Koperasi $koperasi = null, array $attributes = []): User
{
    test()->seed(PermissionSeeder::class);

    $koperasi ??= Koperasi::create(['nama' => 'Koperasi Test '.uniqid()]);

    $role = Role::query()
        ->where('name', 'admin_primer')
        ->where('guard_name', 'web')
        ->where('koperasi_id', $koperasi->id)
        ->first();

    if (! $role) {
        $role = new Role(['name' => 'admin_primer', 'guard_name' => 'web']);
        $role->koperasi_id = $koperasi->id;
        $role->save();
    }

    $role->syncPermissions(PermissionCatalog::adminPrimerTemplate());

    $user = User::factory()->create($attributes);
    $user->koperasi_id = $koperasi->id;
    $user->save();
    $user->assignRole($role);

    return $user;
}

/** Buat request produk melalui service agar invariant tiket dan history ikut diuji. */
function productRequestFor(User $actor, array $overrides = []): ProductRequest
{
    test()->actingAs($actor);

    return app(ProductRequestService::class)->create($actor, array_merge([
        'type' => 'feature',
        'module' => 'inventaris',
        'title' => 'Tambahkan ringkasan inventaris per lokasi',
        'description' => 'Kami membutuhkan ringkasan agregat inventaris berdasarkan lokasi penempatan.',
        'requester_priority' => 'normal',
        'attachments' => [],
    ], $overrides));
}

/** Buat anggota tenant custom yang hanya mengakses domain request produk. */
function tenantRequestMember(Koperasi $koperasi, string $name = 'Anggota Request'): User
{
    test()->seed(PermissionSeeder::class);
    $role = new Role(['name' => $name.' Role '.uniqid(), 'guard_name' => 'web']);
    $role->koperasi_id = $koperasi->id;
    $role->save();
    $role->syncPermissions([
        'product-request.view',
        'product-request.create',
        'product-request.reply',
        'product-request.close',
    ]);
    $user = User::factory()->create(['name' => $name, 'koperasi_id' => $koperasi->id]);
    $user->assignRole($role);

    return $user;
}
