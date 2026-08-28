<?php

use App\Models\Koperasi;
use App\Repositories\OwnerProductRequestRepository;
use App\Services\ProductRequestMessageService;
use App\Services\ProductRequestTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('only system owner can open global product request workspace', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin, ['title' => 'Request untuk workspace owner']);

    $this->actingAs($admin)
        ->get(route('owner.product-requests.index'))
        ->assertForbidden();

    $superAdmin = superAdminUser();
    $this->actingAs($superAdmin)
        ->get(route('owner.product-requests.show', $request->ticket_number))
        ->assertForbidden();

    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->get(route('owner.product-requests.index'))
        ->assertOk()
        ->assertSee($request->ticket_number)
        ->assertSee('Request untuk workspace owner');
    $this->get(route('owner.product-requests.show', $request->ticket_number))
        ->assertOk()
        ->assertSee($request->description);
});

test('public owner reply fills first response once changes submitted to review and reaches tenant timeline', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();
    $service = app(ProductRequestMessageService::class);

    $this->travelTo(now()->addHour());
    $this->actingAs($owner);
    $service->ownerMessage($owner, $request, [
        'visibility' => 'public',
        'body' => 'Kami mulai meninjau kebutuhan ini.',
        'attachments' => [],
    ]);
    $firstResponse = DB::table('product_requests')->where('id', $request->id)->value('first_responded_at');

    $this->travel(30)->minutes();
    $service->ownerMessage($owner, $request, [
        'visibility' => 'public',
        'body' => 'Informasi kedua dari tim produk.',
        'attachments' => [],
    ]);

    expect(DB::table('product_requests')->where('id', $request->id)->value('first_responded_at'))->toBe($firstResponse)
        ->and(DB::table('product_requests')->where('id', $request->id)->value('status'))->toBe('under_review')
        ->and(DB::table('product_request_status_histories')->where('to_status', 'under_review')->count())->toBe(1);

    $this->actingAs($admin)
        ->get(route('product-requests.show', $request->ticket_number))
        ->assertOk()
        ->assertSee('Kami mulai meninjau kebutuhan ini.')
        ->assertSee('Informasi kedua dari tim produk.');
});

test('owner triage enforces transition map records history assignment priority and terminal timestamps', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser(['name' => 'Owner Penanggung Jawab']);
    $service = app(ProductRequestTransitionService::class);
    $this->actingAs($owner);

    expect(fn () => $service->updateOwnerTriage($owner, $request, [
        'status' => 'in_progress',
        'assigned_to' => $owner->id,
        'internal_priority' => 'high',
    ]))->toThrow(ValidationException::class);

    foreach (['under_review', 'considered', 'planned', 'in_progress', 'completed'] as $status) {
        $current = app(OwnerProductRequestRepository::class)->find($request->ticket_number);
        $service->updateOwnerTriage($owner, $current, [
            'status' => $status,
            'assigned_to' => $owner->id,
            'internal_priority' => 'high',
            'reason' => "Berpindah ke {$status}",
        ]);
    }

    $row = DB::table('product_requests')->where('id', $request->id)->first();
    expect($row->status)->toBe('completed')
        ->and((int) $row->assigned_to)->toBe((int) $owner->id)
        ->and($row->internal_priority)->toBe('high')
        ->and($row->resolved_at)->not->toBeNull()
        ->and(DB::table('product_request_status_histories')->where('product_request_id', $request->id)->count())->toBe(6);
});

test('owner cannot assign tenant user as product owner', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();
    $this->actingAs($owner);

    expect(fn () => app(ProductRequestTransitionService::class)->updateOwnerTriage($owner, $request, [
        'status' => 'under_review',
        'assigned_to' => $admin->id,
        'internal_priority' => null,
    ]))->toThrow(ValidationException::class);

    expect(DB::table('product_requests')->where('id', $request->id)->value('assigned_to'))->toBeNull();
});

test('duplicate source must be another request from the same koperasi and remains safely linked', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Duplicate A']);
    $adminA = adminPrimerUser($koperasiA);
    $source = productRequestFor($adminA, ['title' => 'Sumber request asli']);
    $duplicate = productRequestFor($adminA, ['title' => 'Request yang sama']);
    $adminB = adminPrimerUser(Koperasi::create(['nama' => 'Koperasi Duplicate B']));
    $foreign = productRequestFor($adminB, ['title' => 'Request koperasi lain']);
    $owner = systemOwnerUser();
    $service = app(ProductRequestTransitionService::class);
    $this->actingAs($owner);

    expect(fn () => $service->updateOwnerTriage($owner, $duplicate, [
        'status' => 'duplicate',
        'duplicate_ticket' => $foreign->ticket_number,
        'internal_priority' => null,
        'assigned_to' => null,
    ]))->toThrow(ValidationException::class);

    $service->updateOwnerTriage($owner, $duplicate, [
        'status' => 'duplicate',
        'duplicate_ticket' => $source->ticket_number,
        'internal_priority' => 'normal',
        'assigned_to' => $owner->id,
    ]);

    expect((int) DB::table('product_requests')->where('id', $duplicate->id)->value('duplicate_of_id'))->toBe((int) $source->id);
    $this->actingAs($adminA)
        ->get(route('product-requests.show', $duplicate->ticket_number))
        ->assertOk()
        ->assertSee($source->ticket_number)
        ->assertSee('Sumber request asli')
        ->assertDontSee($foreign->ticket_number);
});

test('owner mutations are audited without body internal note or sensitive reason', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();
    $secret = 'ISI-CATATAN-INTERNAL-SANGAT-RAHASIA';

    $this->actingAs($owner)
        ->post(route('owner.product-requests.messages.store', $request->ticket_number), [
            'visibility' => 'internal',
            'body' => $secret,
        ])->assertRedirect();

    $audit = DB::table('system_owner_audit_logs')->latest('id')->first();
    expect($audit->action)->toBe('owner.product-requests.messages.store')
        ->and(str_contains((string) $audit->filters, $secret))->toBeFalse()
        ->and(str_contains((string) $audit->route, $secret))->toBeFalse();
});

test('owner dashboard shows aggregate request pulse without conversation content', function () {
    $admin = adminPrimerUser();
    productRequestFor($admin, [
        'title' => 'JUDUL-SENSITIF-TIDAK-DI-DASHBOARD',
        'description' => 'BODY-SENSITIF-TIDAK-DI-DASHBOARD',
    ]);
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Pusat request produk')
        ->assertSee('Backlog')
        ->assertDontSee('JUDUL-SENSITIF-TIDAK-DI-DASHBOARD')
        ->assertDontSee('BODY-SENSITIF-TIDAK-DI-DASHBOARD');
});
