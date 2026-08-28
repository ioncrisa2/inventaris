<?php

use App\Models\ProductRequestMessage;
use App\Services\ProductRequestAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('valid private attachment stores sanitized metadata checksum and downloads only through authorized route', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $file = UploadedFile::fake()->createWithContent('../bukti kebutuhan.pdf', '%PDF-1.4 request product');
    $request = productRequestFor($admin, ['attachments' => [$file]]);
    $attachment = DB::table('product_request_attachments')->where('product_request_id', $request->id)->first();

    expect($attachment->disk)->toBe('local')
        ->and($attachment->original_name)->toBe('bukti kebutuhan.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->checksum)->toBe(hash('sha256', '%PDF-1.4 request product'))
        ->and(str_contains($attachment->path, 'bukti kebutuhan.pdf'))->toBeFalse();
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($admin)
        ->get(route('product-requests.attachments.download', [$request->ticket_number, $attachment->id]))
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');

    $owner = systemOwnerUser();
    $this->actingAs($owner)
        ->get(route('owner.product-requests.attachments.download', [$request->ticket_number, $attachment->id]))
        ->assertOk();
});

test('attachment validation rejects count size extension and mime mismatches', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $base = [
        'type' => 'feature',
        'module' => 'inventaris',
        'title' => 'Validasi lampiran request produk',
        'description' => 'Request ini sengaja dipakai untuk menguji seluruh batas validasi lampiran.',
        'requester_priority' => 'normal',
    ];

    $this->actingAs($admin)->post(route('product-requests.store'), $base + [
        'attachments' => array_map(
            fn (int $index) => UploadedFile::fake()->create("file-{$index}.pdf", 10, 'application/pdf'),
            range(1, 4),
        ),
    ])->assertSessionHasErrors('attachments');

    $this->post(route('product-requests.store'), $base + [
        'attachments' => [UploadedFile::fake()->create('terlalu-besar.pdf', 5121, 'application/pdf')],
    ])->assertSessionHasErrors('attachments.0');

    $this->post(route('product-requests.store'), $base + [
        'attachments' => [UploadedFile::fake()->create('program.exe', 10, 'application/x-msdownload')],
    ])->assertSessionHasErrors('attachments.0');

    $this->post(route('product-requests.store'), $base + [
        'attachments' => [UploadedFile::fake()->create('palsu.pdf', 10, 'text/plain')],
    ])->assertSessionHasErrors('attachments.0');

    expect(DB::table('product_requests')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('request attachment capacity counts existing files and rolls back reply when exceeded', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $now = now();

    DB::table('product_request_attachments')->insert(collect(range(1, 10))->map(fn (int $index) => [
        'product_request_id' => $request->id,
        'message_id' => null,
        'uploaded_by' => $admin->id,
        'disk' => 'local',
        'path' => "fixture/{$index}.pdf",
        'original_name' => "fixture-{$index}.pdf",
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'checksum' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all());

    $this->actingAs($admin)
        ->post(route('product-requests.messages.store', $request->ticket_number), [
            'body' => 'Mencoba menambah file kesebelas.',
            'attachments' => [UploadedFile::fake()->create('kesebelas.pdf', 1, 'application/pdf')],
        ])->assertSessionHasErrors('attachments');

    expect(DB::table('product_request_messages')->count())->toBe(0)
        ->and(DB::table('product_request_attachments')->count())->toBe(10);
});

test('total attachment bytes are enforced across submissions', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    DB::table('product_request_attachments')->insert([
        'product_request_id' => $request->id,
        'message_id' => null,
        'uploaded_by' => $admin->id,
        'disk' => 'local',
        'path' => 'fixture/almost-full.pdf',
        'original_name' => 'almost-full.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => (20 * 1024 * 1024) - 1024,
        'checksum' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('product-requests.messages.store', $request->ticket_number), [
            'body' => 'Mencoba melewati total dua puluh megabita.',
            'attachments' => [UploadedFile::fake()->create('tambahan.pdf', 2, 'application/pdf')],
        ])->assertSessionHasErrors('attachments');

    expect(DB::table('product_request_messages')->count())->toBe(0);
});

test('stored files and rows are reverted when outer database transaction rolls back', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $this->actingAs($admin);
    $service = app(ProductRequestAttachmentService::class);

    expect(fn () => DB::transaction(function () use ($service, $request, $admin) {
        $service->store(
            $request,
            null,
            $admin,
            [UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf')],
        );

        throw new RuntimeException('Paksa rollback lampiran.');
    }))->toThrow(RuntimeException::class);

    expect(DB::table('product_request_attachments')->where('product_request_id', $request->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('attachment service rejects a message belonging to another request before writing file or row', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $first = productRequestFor($admin, ['title' => 'Induk lampiran pertama']);
    $second = productRequestFor($admin, ['title' => 'Induk pesan kedua']);
    $message = ProductRequestMessage::query()->create([
        'product_request_id' => $second->id,
        'author_user_id' => $admin->id,
        'visibility' => 'public',
        'body' => 'Pesan milik request kedua.',
    ]);
    $this->actingAs($admin);

    expect(fn () => DB::transaction(fn () => app(ProductRequestAttachmentService::class)->store(
        $first,
        $message,
        $admin,
        [UploadedFile::fake()->create('spoof.pdf', 5, 'application/pdf')],
    )))->toThrow(LogicException::class);

    expect(DB::table('product_request_attachments')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('internal note cannot carry hidden attachments that would affect tenant quota', function () {
    Storage::fake('local');
    $admin = adminPrimerUser();
    $request = productRequestFor($admin);
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->post(route('owner.product-requests.messages.store', $request->ticket_number), [
            'visibility' => 'internal',
            'body' => 'Catatan internal tanpa lampiran tersembunyi.',
            'attachments' => [UploadedFile::fake()->create('internal.pdf', 5, 'application/pdf')],
        ])->assertSessionHasErrors('attachments');

    expect(DB::table('product_request_messages')->count())->toBe(0)
        ->and(DB::table('product_request_attachments')->count())->toBe(0);
});
