<?php

use App\Models\Koperasi;
use App\Models\ProductRequest;
use App\Repositories\OwnerProductRequestRepository;
use App\Services\ProductRequestMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('tenant cannot list show reply close or download another koperasi request', function () {
    Storage::fake('local');
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);
    $requestA = productRequestFor($adminA, [
        'title' => 'Judul rahasia koperasi alpha',
        'description' => 'Isi percakapan privat koperasi alpha yang tidak boleh bocor.',
        'attachments' => [UploadedFile::fake()->create('alpha.pdf', 100, 'application/pdf')],
    ]);
    $attachmentId = DB::table('product_request_attachments')->where('product_request_id', $requestA->id)->value('id');

    $this->actingAs($adminB)
        ->get(route('product-requests.index', ['search' => 'alpha']))
        ->assertOk()
        ->assertDontSee($requestA->ticket_number)
        ->assertDontSee('Judul rahasia koperasi alpha')
        ->assertDontSee('Isi percakapan privat koperasi alpha');

    $this->get(route('product-requests.show', $requestA->ticket_number))->assertNotFound();
    $this->post(route('product-requests.messages.store', $requestA->ticket_number), ['body' => 'IDOR reply'])->assertNotFound();
    $this->patch(route('product-requests.state.toggle', $requestA->ticket_number))->assertNotFound();
    $this->get(route('product-requests.attachments.download', [$requestA->ticket_number, $attachmentId]))->assertNotFound();

    expect(DB::table('product_request_messages')->count())->toBe(0)
        ->and(DB::table('product_requests')->where('id', $requestA->id)->value('status'))->toBe('submitted');
});

test('custom member cannot see another member request even in the same koperasi', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Anggota']);
    $memberA = tenantRequestMember($koperasi, 'Anggota A');
    $memberB = tenantRequestMember($koperasi, 'Anggota B');
    $requestA = productRequestFor($memberA, ['title' => 'Request anggota A yang privat']);

    $this->actingAs($memberB)
        ->get(route('product-requests.index'))
        ->assertOk()
        ->assertDontSee($requestA->ticket_number)
        ->assertDontSee('Request anggota A yang privat');
    $this->get(route('product-requests.show', $requestA->ticket_number))->assertNotFound();
});

test('nested attachment id from another request is rejected even within one tenant', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $first = productRequestFor($admin, ['title' => 'Request pertama tanpa file']);
    $second = productRequestFor($admin, [
        'title' => 'Request kedua dengan file',
        'attachments' => [UploadedFile::fake()->create('kedua.pdf', 50, 'application/pdf')],
    ]);
    $attachmentId = DB::table('product_request_attachments')->where('product_request_id', $second->id)->value('id');

    $this->actingAs($admin)
        ->get(route('product-requests.attachments.download', [$first->ticket_number, $attachmentId]))
        ->assertNotFound();
});

test('internal note is absent from tenant timeline counts attachments and public activity timestamp', function () {
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $publicActivity = $request->last_activity_at->toDateTimeString();
    $owner = systemOwnerUser();

    $this->actingAs($owner);
    app(ProductRequestMessageService::class)->ownerMessage($owner, $request, [
        'visibility' => 'internal',
        'body' => 'CATATAN-RAHASIA-OWNER yang sama sekali tidak boleh terlihat tenant.',
        'attachments' => [],
    ]);

    expect(DB::table('product_request_messages')->where('visibility', 'internal')->count())->toBe(1)
        ->and(DB::table('product_requests')->where('id', $request->id)->value('last_activity_at'))->toBe($publicActivity)
        ->and(DB::table('product_request_attachments')->where('product_request_id', $request->id)->count())->toBe(0);

    $response = $this->actingAs($admin)->get(route('product-requests.show', $request->ticket_number));
    $response->assertOk()
        ->assertDontSee('CATATAN-RAHASIA-OWNER')
        ->assertDontSee('Catatan internal')
        ->assertDontSee('Hanya system owner');
});

test('owner model scope stays fail closed while explicit owner repository can read all valid tenants', function () {
    $adminA = adminPrimerUser(Koperasi::create(['nama' => 'Scope A']));
    $requestA = productRequestFor($adminA, ['title' => 'Request scope A']);
    $adminB = adminPrimerUser(Koperasi::create(['nama' => 'Scope B']));
    $requestB = productRequestFor($adminB, ['title' => 'Request scope B']);
    $owner = systemOwnerUser();

    $this->actingAs($owner);
    expect(ProductRequest::query()->count())->toBe(0);

    $paginator = app(OwnerProductRequestRepository::class)->paginate([], 10);
    expect($paginator->total())->toBe(2)
        ->and($paginator->pluck('id')->all())->toContain($requestA->id, $requestB->id);
});
