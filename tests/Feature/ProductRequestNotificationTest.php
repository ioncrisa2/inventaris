<?php

use App\Events\ProductRequestActivity;
use App\Models\Koperasi;
use App\Services\ProductRequestMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('new request notification reaches system owners only with sanitized payload', function () {
    $owner = systemOwnerUser(['name' => 'Owner Notifikasi']);
    $superAdmin = superAdminUser();
    $admin = adminPrimerUser();
    $otherTenant = adminPrimerUser(Koperasi::create(['nama' => 'Tenant Notifikasi Lain']));
    $request = productRequestFor($admin, [
        'title' => 'JUDUL-RAHASIA-NOTIFIKASI',
        'description' => 'BODY-RAHASIA-NOTIFIKASI',
    ]);

    $rows = DB::table('notifications')->get();
    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->notifiable_id)->toBe((int) $owner->id);

    $payload = (string) $rows->first()->data;
    expect($payload)->toContain($request->ticket_number)
        ->and(str_contains($payload, 'JUDUL-RAHASIA-NOTIFIKASI'))->toBeFalse()
        ->and(str_contains($payload, 'BODY-RAHASIA-NOTIFIKASI'))->toBeFalse()
        ->and(str_contains($payload, 'path'))->toBeFalse();

    expect(DB::table('notifications')->whereIn('notifiable_id', [$superAdmin->id, $admin->id, $otherTenant->id])->count())->toBe(0);
});

test('tenant reply notifies assigned owner rather than every owner', function () {
    $ownerA = systemOwnerUser(['name' => 'Owner A']);
    $ownerB = systemOwnerUser(['name' => 'Owner B']);
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    DB::table('product_requests')->where('id', $request->id)->update(['assigned_to' => $ownerB->id]);
    DB::table('notifications')->delete();

    ProductRequestActivity::dispatch($request->id, 'tenant_replied', $admin->id);

    expect(DB::table('notifications')->where('notifiable_id', $ownerA->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $ownerB->id)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $admin->id)->count())->toBe(0);
});

test('public owner update notifies same-tenant public participants but no unrelated tenant', function () {
    $koperasi = Koperasi::create(['nama' => 'Tenant Peserta']);
    $creator = tenantRequestMember($koperasi, 'Pengaju Notifikasi');
    $admin = adminPrimerUser($koperasi, ['name' => 'Admin Peserta']);
    $unrelated = adminPrimerUser(Koperasi::create(['nama' => 'Tenant Asing']));
    $request = productRequestFor($creator);

    $this->actingAs($admin);
    app(ProductRequestMessageService::class)->tenantReply($admin, $request, [
        'body' => 'Admin primer ikut memberi konteks publik.',
        'attachments' => [],
    ]);
    $owner = systemOwnerUser();
    ProductRequestActivity::dispatch($request->id, 'owner_replied', $owner->id);

    expect(DB::table('notifications')->where('notifiable_id', $creator->id)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $admin->id)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $unrelated->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $owner->id)->count())->toBe(0);
});

test('internal note notification never reaches a tenant and does not include note body', function () {
    $ownerA = systemOwnerUser(['name' => 'Owner Pencatat']);
    $ownerB = systemOwnerUser(['name' => 'Owner Assignee']);
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    DB::table('product_requests')->where('id', $request->id)->update(['assigned_to' => $ownerB->id]);
    DB::table('notifications')->delete();

    ProductRequestActivity::dispatch($request->id, 'internal_note', $ownerA->id);

    expect(DB::table('notifications')->where('notifiable_id', $admin->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $ownerB->id)->count())->toBe(1);

    $payload = (string) DB::table('notifications')->where('notifiable_id', $ownerB->id)->value('data');
    expect($payload)->toContain('internal_note')
        ->and(str_contains($payload, 'body'))->toBeFalse()
        ->and(str_contains($payload, 'description'))->toBeFalse();
});

test('notification open endpoint is scoped to recipient and marks owned notification as read', function () {
    $owner = systemOwnerUser();
    $otherOwner = systemOwnerUser();
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $notification = DB::table('notifications')->where('notifiable_id', $owner->id)->first();

    $this->actingAs($admin)
        ->patch(route('notifications.open', $notification->id))
        ->assertNotFound();

    $this->actingAs($owner)
        ->patch(route('notifications.open', $notification->id))
        ->assertRedirect(route('owner.product-requests.show', $request->ticket_number));

    expect(DB::table('notifications')->where('id', $notification->id)->value('read_at'))->not->toBeNull();
    expect(DB::table('notifications')->where('notifiable_id', $otherOwner->id)->whereNull('read_at')->count())->toBe(1);
});
