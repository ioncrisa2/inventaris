<?php

use App\Contracts\VirusScanner;
use App\Exceptions\ScannerUnavailableException;
use App\Jobs\ProcessStoredImage;
use App\Jobs\ScanStoredFile;
use App\Models\Barang;
use App\Models\Koperasi;
use App\Models\ProductRequestAttachment;
use App\Models\StoredFile;
use App\Models\StoredFileAudit;
use App\Models\UnitKerja;
use App\Notifications\StoredFileRejected;
use App\Services\AsyncUploadService;
use App\Services\MediaCleanupService;
use App\Services\StoredFileService;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config()->set('uploads.features.async', true);
    config()->set('uploads.features.scan_required', false);
    config()->set('uploads.features.x_accel', false);
    $this->user = adminUser();
    $this->actingAs($this->user);
});

test('endpoint asinkron dapat dimatikan dengan feature flag', function () {
    config()->set('uploads.features.async', false);

    $this->postJson(route('uploads.store'), [
        'policy' => 'asset_photo',
        'file' => UploadedFile::fake()->image('barang.jpg', 640, 480),
    ])->assertNotFound();

    expect(StoredFile::query()->count())->toBe(0);
});

test('upload asinkron membuat staging privat dan mengantrekan proses gambar', function () {
    Queue::fake();

    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'asset_photo',
        'file' => UploadedFile::fake()->image('barang.jpg', 1200, 800),
    ])->assertAccepted()
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('scan_status', 'not_required')
        ->assertJsonPath('name', 'barang.jpg')
        ->assertJsonStructure(['uuid', 'expires_at']);

    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();

    expect($stored->koperasi_id)->toBe($this->user->koperasi_id)
        ->and($stored->uploaded_by)->toBe($this->user->id)
        ->and($stored->owner_id)->toBeNull()
        ->and($stored->expires_at->isFuture())->toBeTrue()
        ->and($stored->staging_path)->toStartWith('staging/'.$this->user->koperasi_id.'/')
        ->and(StoredFileAudit::query()->where('event', 'upload')->count())->toBe(1);

    Storage::disk('local')->assertExists($stored->staging_path);
    Storage::disk('public')->assertDirectoryEmpty('/');
    Queue::assertPushed(ProcessStoredImage::class, fn ($job) => $job->storedFile->is($stored));
});

test('status staging hanya dapat dibaca dan dibatalkan uploader pada tenant yang sama', function () {
    Queue::fake();
    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'business_documents',
        'file' => UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n%%EOF\n"),
    ])->assertAccepted();
    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();

    $otherTenant = Koperasi::create(['nama' => 'Koperasi Lain']);
    $other = adminPrimerUser($otherTenant);
    $this->actingAs($other)
        ->getJson(route('uploads.show', $stored))
        ->assertForbidden();
    $this->deleteJson(route('uploads.destroy', $stored))->assertForbidden();

    $this->actingAs($this->user)
        ->getJson(route('uploads.show', $stored))
        ->assertOk()
        ->assertJsonPath('uuid', $stored->uuid);
    $this->deleteJson(route('uploads.destroy', $stored))->assertNoContent();

    $stored->refresh();
    expect($stored->status)->toBe('canceled')
        ->and($stored->staging_path)->toBeNull()
        ->and(StoredFileAudit::query()->where('event', 'cancel')->count())->toBe(1);
});

test('claim token terikat actor tenant policy dan owner serta idempotent', function () {
    Queue::fake();
    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'asset_photo',
        'file' => UploadedFile::fake()->image('sampul.jpg'),
    ])->assertAccepted();
    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();
    $unit = UnitKerja::create(['nama_unit' => 'Media', 'kode' => 'MED']);
    $barang = Barang::create([
        'kode_barang' => 'ASY-001',
        'nama_barang' => 'Barang Async',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unit->id,
        'tanggal_perolehan' => now()->subDay(),
        'harga_perolehan' => 100000,
    ]);

    $service = app(AsyncUploadService::class);
    $first = $service->claim($stored, $barang, $this->user, 'asset_photo', 'foto_sampul');
    $second = $service->claim($stored->refresh(), $barang, $this->user, 'asset_photo', 'foto_sampul');

    expect($first->owner->is($barang))->toBeTrue()
        ->and($second->id)->toBe($first->id)
        ->and($second->collection)->toBe('foto_sampul')
        ->and(StoredFileAudit::query()->where('event', 'claim')->count())->toBe(1);

    expect(fn () => $service->claim($stored->refresh(), $barang, $this->user, 'employee_photo', 'foto_sampul'))
        ->toThrow(HttpException::class);
});

test('token kedaluwarsa dan token yang telah dipakai owner lain ditolak', function () {
    Queue::fake();
    $service = app(AsyncUploadService::class);
    $stored = $service->stage(
        UploadedFile::fake()->image('sampul.jpg'),
        'asset_photo',
        $this->user,
    );
    $unit = UnitKerja::create(['nama_unit' => 'Gudang', 'kode' => 'GDG']);
    $barangA = Barang::create([
        'kode_barang' => 'ASY-A', 'nama_barang' => 'Barang A', 'kategori' => 'Lainnya',
        'unit_kerja_id' => $unit->id, 'tanggal_perolehan' => now()->subDay(), 'harga_perolehan' => 1000,
    ]);
    $barangB = Barang::create([
        'kode_barang' => 'ASY-B', 'nama_barang' => 'Barang B', 'kategori' => 'Lainnya',
        'unit_kerja_id' => $unit->id, 'tanggal_perolehan' => now()->subDay(), 'harga_perolehan' => 1000,
    ]);

    $service->claim($stored, $barangA, $this->user, 'asset_photo', 'foto_sampul');
    expect(fn () => $service->claim($stored->refresh(), $barangB, $this->user, 'asset_photo', 'foto_sampul'))
        ->toThrow(HttpException::class);

    $expired = $service->stage(
        UploadedFile::fake()->image('expired.jpg'),
        'asset_photo',
        $this->user,
    );
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(fn () => $service->claim($expired->refresh(), $barangB, $this->user, 'asset_photo', 'foto_sampul'))
        ->toThrow(HttpException::class);
});

test('file pending scan tidak dapat dipreview atau diunduh', function () {
    Queue::fake();
    config()->set('uploads.features.scan_required', true);
    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'business_documents',
        'file' => UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n%%EOF\n"),
    ])->assertAccepted()->assertJsonPath('status', 'pending_scan');
    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();

    $this->get(route('uploads.preview', $stored))->assertStatus(423);
    $this->get(route('uploads.download', $stored))->assertStatus(423);
    Queue::assertPushed(ScanStoredFile::class);
});

test('scanner bersih meneruskan pipeline dan scanner terinfeksi menghapus staging', function () {
    Queue::fake();
    Notification::fake();
    config()->set('uploads.features.scan_required', true);
    $stored = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->image('scan.jpg'),
        'asset_photo',
        $this->user,
    );

    $cleanScanner = Mockery::mock(VirusScanner::class);
    $cleanScanner->shouldReceive('scan')->once()->andReturn('clean');
    app()->instance(VirusScanner::class, $cleanScanner);
    app()->call([new ScanStoredFile($stored), 'handle']);

    expect($stored->refresh()->scan_status)->toBe('clean')
        ->and($stored->status)->toBe('processing');
    Queue::assertPushed(ProcessStoredImage::class);

    $infected = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->image('infected.jpg'),
        'asset_photo',
        $this->user,
    );
    $infectedPath = $infected->staging_path;
    $infectedScanner = Mockery::mock(VirusScanner::class);
    $infectedScanner->shouldReceive('scan')->once()->andReturn('infected');
    app()->instance(VirusScanner::class, $infectedScanner);
    app()->call([new ScanStoredFile($infected), 'handle']);

    expect($infected->refresh()->status)->toBe('infected')
        ->and($infected->scan_status)->toBe('infected')
        ->and($infected->expires_at->isAfter(now()->addDays(89)))->toBeTrue();
    Storage::disk('local')->assertMissing($infectedPath);
    Notification::assertSentTo($this->user, StoredFileRejected::class);
});

test('scanner tidak tersedia mempertahankan status pending untuk retry', function () {
    Queue::fake();
    config()->set('uploads.features.scan_required', true);
    $stored = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->image('retry.jpg'),
        'asset_photo',
        $this->user,
    );
    $scanner = Mockery::mock(VirusScanner::class);
    $scanner->shouldReceive('scan')->once()->andThrow(new ScannerUnavailableException('offline'));
    app()->instance(VirusScanner::class, $scanner);

    expect(fn () => app()->call([new ScanStoredFile($stored), 'handle']))
        ->toThrow(ScannerUnavailableException::class);

    expect($stored->refresh()->status)->toBe('pending_scan')
        ->and($stored->scan_status)->toBe('pending')
        ->and($stored->failure_code)->toBe('scanner_unavailable');
    Storage::disk('local')->assertExists($stored->staging_path);
});

test('job gambar menghasilkan webp ready dan menghapus raw staging', function () {
    Queue::fake();
    $stored = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->image('besar.jpg', 1800, 1200),
        'asset_photo',
        $this->user,
    );
    $rawPath = $stored->staging_path;

    app()->call([new ProcessStoredImage($stored), 'handle']);

    $stored->refresh()->load('variants');
    expect($stored->status)->toBe('ready')
        ->and($stored->extension)->toBe('webp')
        ->and($stored->staging_path)->toBeNull()
        ->and($stored->variants->pluck('name')->all())->toContain('canonical', 'thumbnail');
    Storage::disk('local')->assertMissing($rawPath);
    Storage::disk('public')->assertExists($stored->path);
});

test('cleanup menghapus staging kedaluwarsa menandai referensi hilang dan requeue scan macet', function () {
    Queue::fake();
    $service = app(AsyncUploadService::class);
    $expired = $service->stage(
        UploadedFile::fake()->image('expired.jpg'),
        'asset_photo',
        $this->user,
    );
    $expiredPath = $expired->staging_path;
    StoredFile::query()->whereKey($expired->id)->update(['expires_at' => now()->subMinute()]);

    $missing = StoredFile::query()->create([
        'uuid' => (string) Str::uuid(), 'koperasi_id' => $this->user->koperasi_id,
        'uploaded_by' => $this->user->id, 'policy' => 'business_documents', 'collection' => 'dokumen',
        'status' => 'ready', 'scan_status' => 'clean', 'disk' => 'local', 'path' => 'hilang/dokumen.pdf',
        'original_name' => 'dokumen.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf',
        'source_size_bytes' => 10, 'final_size_bytes' => 10,
        'source_checksum_sha256' => hash('sha256', 'a'), 'final_checksum_sha256' => hash('sha256', 'a'),
    ]);

    config()->set('uploads.features.scan_required', true);
    $stalled = $service->stage(
        UploadedFile::fake()->image('stalled.jpg'),
        'asset_photo',
        $this->user,
    );
    StoredFile::query()->whereKey($stalled->id)->update(['updated_at' => now()->subDays(2)]);

    $stats = app(MediaCleanupService::class)->run(chunk: 1);

    expect($stats)->toMatchArray(['expired' => 1, 'missing' => 1, 'requeued' => 1])
        ->and(StoredFile::query()->find($expired->id))->toBeNull()
        ->and($missing->refresh()->status)->toBe('failed')
        ->and($missing->failure_code)->toBe('stored_bytes_missing')
        ->and($stalled->refresh()->failure_code)->toBe('scan_stalled');
    Storage::disk('local')->assertMissing($expiredPath);
    Queue::assertPushed(ScanStoredFile::class, fn ($job) => $job->storedFile->is($stalled));
});

test('x accel hanya mengirim internal redirect setelah file siap dan terotorisasi', function () {
    config()->set('uploads.features.x_accel', true);
    $path = 'tenants/'.$this->user->koperasi_id.'/business_documents/2026/09/'.Str::uuid().'.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");
    $stored = StoredFile::query()->create([
        'uuid' => (string) Str::uuid(),
        'koperasi_id' => $this->user->koperasi_id,
        'uploaded_by' => $this->user->id,
        'policy' => 'business_documents',
        'collection' => 'staging',
        'status' => 'ready',
        'scan_status' => 'clean',
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'dokumen.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'source_size_bytes' => 15,
        'final_size_bytes' => 15,
        'source_checksum_sha256' => hash('sha256', 'source'),
        'final_checksum_sha256' => hash('sha256', 'final'),
    ]);
    $stored->variants()->create([
        'name' => 'canonical', 'disk' => 'local', 'path' => $path,
        'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 15,
        'checksum_sha256' => hash('sha256', 'final'),
    ]);

    $this->get(route('uploads.download', $stored))
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/protected-files/'.str_replace('/', '/', $path))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

test('emergency capacity guard menolak upload dengan status 507', function () {
    config()->set('uploads.capacity.emergency_min_free_bytes', PHP_INT_MAX);

    $this->postJson(route('uploads.store'), [
        'policy' => 'asset_photo',
        'file' => UploadedFile::fake()->image('penuh.jpg'),
    ])->assertStatus(507)
        ->assertJsonPath('message', 'Upload sementara ditolak karena kapasitas penyimpanan server hampir habis.');
});

test('system owner dapat upload ke tenant request yang eksplisit lalu claim token tanpa bypass tenant', function () {
    Queue::fake();
    $tenant = tenantRequestMember($this->user->koperasi, 'Pemohon Async');
    $request = productRequestFor($tenant);
    $owner = systemOwnerUser();
    $this->actingAs($owner);

    $this->postJson(route('uploads.store'), [
        'policy' => 'product_attachments',
        'file' => UploadedFile::fake()->createWithContent('catatan.txt', 'Lampiran aman'),
    ])->assertUnprocessable()->assertJsonValidationErrors('koperasi_id');

    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'product_attachments',
        'koperasi_id' => $request->koperasi_id,
        'file' => UploadedFile::fake()->createWithContent('catatan.txt', 'Lampiran aman'),
    ])->assertAccepted();
    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();

    $this->post(route('owner.product-requests.messages.store', $request->ticket_number), [
        'visibility' => 'public',
        'body' => 'Lampiran sudah ditambahkan untuk tenant.',
        'attachments_upload_uuids' => [$stored->uuid],
    ])->assertRedirect(route('owner.product-requests.show', $request->ticket_number));

    $attachment = ProductRequestAttachment::query()->latest('id')->firstOrFail();
    expect($stored->refresh()->koperasi_id)->toBe($request->koperasi_id)
        ->and($stored->uploaded_by)->toBe($owner->id)
        ->and($stored->owner->is($attachment))->toBeTrue()
        ->and($attachment->product_request_id)->toBe($request->id);

    $otherTenant = Koperasi::create(['nama' => 'Tenant target salah']);
    $wrongTarget = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->createWithContent('salah.txt', 'x'),
        'product_attachments',
        $owner,
        targetKoperasiId: $otherTenant->id,
    );
    expect(fn () => app(AsyncUploadService::class)->claim(
        $wrongTarget,
        $attachment,
        $owner,
        'product_attachments',
        'attachment',
    ))->toThrow(HttpException::class);
});

test('fallback multipart tetap memindai raw file dan mencatat hasil clean atau rejection', function () {
    config()->set('uploads.features.async', false);
    config()->set('uploads.features.scan_required', true);
    $cleanScanner = Mockery::mock(VirusScanner::class);
    $cleanScanner->shouldReceive('scan')->once()->andReturn('clean');
    app()->instance(VirusScanner::class, $cleanScanner);
    $cleanFile = UploadedFile::fake()->createWithContent('aman.txt', 'konten aman');

    $validator = Validator::make(['file' => $cleanFile], [
        'file' => UploadPolicy::fileRules('product_attachments', true),
    ]);
    expect($validator->passes())->toBeTrue();

    $unit = UnitKerja::create(['nama_unit' => 'Fallback', 'kode' => 'FBK']);
    $barang = Barang::create([
        'kode_barang' => 'FBK-1', 'nama_barang' => 'Fallback', 'kategori' => 'Lainnya',
        'unit_kerja_id' => $unit->id, 'tanggal_perolehan' => now()->subDay(), 'harga_perolehan' => 1000,
    ]);
    $prepared = app(StoredFileService::class)->prepare($cleanFile, 'product_attachments');
    try {
        $stored = DB::transaction(fn () => app(StoredFileService::class)->persist(
            $prepared,
            $this->user->koperasi_id,
            'fallback',
            $barang,
            $this->user->id,
        ));
    } finally {
        $prepared->cleanup();
    }
    expect($stored->scan_status)->toBe('clean')
        ->and($stored->scanned_at)->not->toBeNull();

    $infectedScanner = Mockery::mock(VirusScanner::class);
    $infectedScanner->shouldReceive('scan')->once()->andReturn('infected');
    app()->instance(VirusScanner::class, $infectedScanner);
    $infected = Validator::make([
        'file' => UploadedFile::fake()->createWithContent('bahaya.txt', 'signature'),
    ], [
        'file' => UploadPolicy::fileRules('product_attachments', true),
    ]);
    expect($infected->fails())->toBeTrue()
        ->and($infected->errors()->first('file'))->toContain('terdeteksi berbahaya')
        ->and(StoredFileAudit::query()->where('event', 'scan_rejected')->exists())->toBeTrue();

    $offlineScanner = Mockery::mock(VirusScanner::class);
    $offlineScanner->shouldReceive('scan')->once()->andThrow(new ScannerUnavailableException('offline'));
    app()->instance(VirusScanner::class, $offlineScanner);
    $offline = Validator::make([
        'file' => UploadedFile::fake()->createWithContent('tertunda.txt', 'konten'),
    ], [
        'file' => UploadPolicy::fileRules('product_attachments', true),
    ]);
    expect($offline->fails())->toBeTrue()
        ->and($offline->errors()->first('file'))->toContain('belum tersedia');
});
