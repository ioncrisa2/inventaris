<?php

use App\Enums\ProductRequestStatus;
use App\Repositories\OwnerProductRequestRepository;
use App\Repositories\ProductRequestRepository;
use App\Services\ProductRequestMessageService;
use App\Services\ProductRequestService;
use App\Services\ProductRequestTransitionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('tenant close and reopen create immutable histories and maintain timestamps', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $service = app(ProductRequestService::class);
    $this->actingAs($admin);

    $closed = $service->toggleClosed($admin, $request, ['reason' => 'Kebutuhan ditunda sementara.']);
    expect($closed->status)->toBe(ProductRequestStatus::Closed)
        ->and($closed->closed_at)->not->toBeNull();

    $reopened = $service->toggleClosed($admin, $closed, ['reason' => 'Kebutuhan aktif kembali.']);
    expect($reopened->status)->toBe(ProductRequestStatus::Submitted)
        ->and($reopened->closed_at)->toBeNull()
        ->and($reopened->resolved_at)->toBeNull()
        ->and(DB::table('product_request_status_histories')->where('product_request_id', $request->id)->count())->toBe(3);

    $this->assertDatabaseHas('product_request_status_histories', [
        'product_request_id' => $request->id,
        'from_status' => 'closed',
        'to_status' => 'submitted',
        'reason' => 'Kebutuhan aktif kembali.',
    ]);
});

test('tenant information reply moves needs information back to review with one status history', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();
    $this->actingAs($owner);
    app(ProductRequestTransitionService::class)->updateOwnerTriage($owner, $request, [
        'status' => 'needs_information',
        'internal_priority' => null,
        'assigned_to' => null,
        'reason' => 'Mohon contoh alur penggunaan.',
    ]);

    $this->actingAs($admin)
        ->get(route('product-requests.show', $request->ticket_number))
        ->assertOk();
    app(ProductRequestMessageService::class)->tenantReply(
        $admin,
        app(ProductRequestRepository::class)->findFor($admin, $request->ticket_number),
        ['body' => 'Contoh alur tambahan sudah kami jelaskan.', 'attachments' => []],
    );

    expect(DB::table('product_requests')->where('id', $request->id)->value('status'))->toBe('under_review')
        ->and(DB::table('product_request_status_histories')->where('product_request_id', $request->id)->count())->toBe(3)
        ->and(DB::table('product_request_status_histories')->where('from_status', 'needs_information')->where('to_status', 'under_review')->count())->toBe(1);
});

test('same owner status updates assignment without creating duplicate history', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();
    $this->actingAs($owner);
    $before = DB::table('product_request_status_histories')->count();

    app(ProductRequestTransitionService::class)->updateOwnerTriage($owner, $request, [
        'status' => 'submitted',
        'assigned_to' => $owner->id,
        'internal_priority' => 'urgent',
        'reason' => 'Tidak ada perubahan status.',
    ]);

    expect(DB::table('product_request_status_histories')->count())->toBe($before)
        ->and((int) DB::table('product_requests')->where('id', $request->id)->value('assigned_to'))->toBe((int) $owner->id)
        ->and(DB::table('product_requests')->where('id', $request->id)->value('internal_priority'))->toBe('urgent');
});

test('duplicate cycle and self reference are rejected transactionally', function () {
    $admin = adminPrimerUser();
    $first = productRequestFor($admin, ['title' => 'Request siklus pertama']);
    $second = productRequestFor($admin, ['title' => 'Request siklus kedua']);
    $owner = systemOwnerUser();
    $service = app(ProductRequestTransitionService::class);
    $this->actingAs($owner);

    expect(fn () => $service->updateOwnerTriage($owner, $first, [
        'status' => 'duplicate',
        'duplicate_ticket' => $first->ticket_number,
        'assigned_to' => null,
        'internal_priority' => null,
    ]))->toThrow(ValidationException::class);

    $service->updateOwnerTriage($owner, $first, [
        'status' => 'duplicate',
        'duplicate_ticket' => $second->ticket_number,
        'assigned_to' => null,
        'internal_priority' => null,
    ]);

    expect(fn () => $service->updateOwnerTriage($owner, $second, [
        'status' => 'duplicate',
        'duplicate_ticket' => $first->ticket_number,
        'assigned_to' => null,
        'internal_priority' => null,
    ]))->toThrow(ValidationException::class);

    expect(DB::table('product_requests')->where('id', $second->id)->value('duplicate_of_id'))->toBeNull();
});

test('database-generated ticket numbers remain unique and unique constraint is final defense', function () {
    $admin = adminPrimerUser();
    $tickets = collect(range(1, 25))->map(fn (int $number) => productRequestFor($admin, [
        'title' => "Request unik nomor {$number}",
    ])->ticket_number);

    expect($tickets->unique())->toHaveCount(25)
        ->and($tickets->every(fn (string $ticket) => preg_match('/^REQ-\d{4}-\d{6,}$/', $ticket) === 1))->toBeTrue();

    $source = app(OwnerProductRequestRepository::class)->find($tickets->first());
    expect(fn () => DB::table('product_requests')->insert([
        'ticket_number' => $source->ticket_number,
        'koperasi_id' => $source->koperasi_id,
        'created_by' => $admin->id,
        'type' => 'feature',
        'title' => 'Duplikat nomor tiket',
        'description' => 'Baris ini harus ditolak oleh unique constraint database.',
        'requester_priority' => 'normal',
        'status' => 'submitted',
        'last_activity_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
