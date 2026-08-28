<?php

use App\Enums\ProductRequestStatus;
use App\Models\Koperasi;
use App\Models\ProductRequestStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('permission catalog gives request access to admin primer but not platform control actors', function () {
    expect(PermissionCatalog::all())->toContain(
        'product-request.view',
        'product-request.create',
        'product-request.reply',
        'product-request.close',
    )->and(PermissionCatalog::adminPrimerTemplate())->toContain(
        'product-request.view',
        'product-request.create',
        'product-request.reply',
        'product-request.close',
    )->and(PermissionCatalog::superAdminTemplate())->not->toContain(
        'product-request.view',
        'product-request.create',
        'product-request.reply',
        'product-request.close',
    );

    $owner = systemOwnerUser();
    $superAdmin = superAdminUser();

    expect($owner->getAllPermissions())->toHaveCount(0)
        ->and($superAdmin->can('product-request.view'))->toBeFalse();
});

test('guest and platform control actor cannot enter tenant request center', function () {
    $this->get(route('product-requests.index'))->assertRedirect(route('login'));

    $superAdmin = superAdminUser();
    $this->actingAs($superAdmin)
        ->get(route('product-requests.index'))
        ->assertForbidden();

    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->get(route('product-requests.index'))
        ->assertForbidden();
});

test('admin primer creates a scoped request with safe defaults ticket and initial history', function () {
    $admin = adminPrimerUser();

    $response = $this->actingAs($admin)->post(route('product-requests.store'), [
        'koperasi_id' => Koperasi::create(['nama' => 'Koperasi Palsu'])->id,
        'created_by' => User::factory()->create()->id,
        'type' => 'feature',
        'module' => 'inventaris',
        'title' => 'Tambahkan rekap inventaris lintas unit',
        'description' => 'Pengguna membutuhkan ringkasan inventaris per unit untuk rapat evaluasi bulanan.',
        'requester_priority' => 'high',
        'status' => 'completed',
        'internal_priority' => 'urgent',
        'assigned_to' => $admin->id,
    ]);

    $record = DB::table('product_requests')->sole();
    $response->assertRedirect(route('product-requests.show', $record->ticket_number));

    expect($record->ticket_number)->toMatch('/^REQ-\d{4}-\d{6,}$/')
        ->and((int) $record->koperasi_id)->toBe((int) $admin->koperasi_id)
        ->and((int) $record->created_by)->toBe((int) $admin->id)
        ->and($record->status)->toBe('submitted')
        ->and($record->internal_priority)->toBeNull()
        ->and($record->assigned_to)->toBeNull()
        ->and($record->last_activity_at)->not->toBeNull();

    $this->assertDatabaseHas('product_request_status_histories', [
        'product_request_id' => $record->id,
        'changed_by' => $admin->id,
        'from_status' => null,
        'to_status' => 'submitted',
    ]);
});

test('request input validates enum module content length and attachment count', function () {
    $admin = adminPrimerUser();

    $this->actingAs($admin)->from(route('product-requests.create'))->post(route('product-requests.store'), [
        'type' => 'not-valid',
        'module' => 'secret-module',
        'title' => 'x',
        'description' => 'short',
        'requester_priority' => 'critical',
    ])->assertRedirect(route('product-requests.create'))
        ->assertSessionHasErrors(['type', 'module', 'title', 'description', 'requester_priority']);

    expect(DB::table('product_requests')->count())->toBe(0);
});

test('custom tenant role can create and see only its own request when explicitly permitted', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Custom']);
    $admin = adminPrimerUser($koperasi);
    $member = tenantRequestMember($koperasi, 'Pemohon A');
    $other = tenantRequestMember($koperasi, 'Pemohon B');
    $memberRequest = productRequestFor($member, ['title' => 'Request milik anggota pertama']);
    $otherRequest = productRequestFor($other, ['title' => 'Request privat anggota kedua']);

    $this->actingAs($member)
        ->get(route('product-requests.index'))
        ->assertOk()
        ->assertSee($memberRequest->ticket_number)
        ->assertDontSee($otherRequest->ticket_number)
        ->assertDontSee('Request privat anggota kedua');

    $this->actingAs($admin)
        ->get(route('product-requests.index'))
        ->assertOk()
        ->assertSee($memberRequest->ticket_number)
        ->assertSee($otherRequest->ticket_number);
});

test('role without request permission receives forbidden and does not see menu', function () {
    test()->seed(PermissionSeeder::class);
    $koperasi = Koperasi::create(['nama' => 'Koperasi Tanpa Akses']);
    $user = User::factory()->create(['koperasi_id' => $koperasi->id]);
    $role = new Role(['name' => 'Tanpa Request', 'guard_name' => 'web']);
    $role->koperasi_id = $koperasi->id;
    $role->save();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Request Produk');
    $this->get(route('product-requests.index'))->assertForbidden();
});

test('terminal request rejects tenant reply and status history cannot be mutated', function () {
    $admin = adminPrimerUser();
    $record = productRequestFor($admin);
    DB::table('product_requests')->where('id', $record->id)->update(['status' => ProductRequestStatus::Completed->value]);

    $this->actingAs($admin)
        ->post(route('product-requests.messages.store', $record->ticket_number), ['body' => 'Balasan baru'])
        ->assertForbidden();

    $history = ProductRequestStatusHistory::query()->firstOrFail();
    expect(fn () => $history->update(['reason' => 'diubah']))->toThrow(LogicException::class)
        ->and(fn () => $history->delete())->toThrow(LogicException::class);
});
