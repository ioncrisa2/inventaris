<?php

use App\Jobs\BuildDocumentPreview;
use App\Jobs\ProcessCalendarImport;
use App\Models\StoredFile;
use App\Services\AsyncUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function minimalValidPdf(): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << >> /Contents 4 0 R >>',
        "<< /Length 0 >>\nstream\n\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 5\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

    return $pdf;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
    config()->set('uploads.features.async', true);
    config()->set('uploads.features.scan_required', false);
    $this->user = adminUser();
    $this->actingAs($this->user);
});

test('pdf valid menghasilkan thumbnail privat lalu baru berstatus ready', function () {
    $stored = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->createWithContent('panduan.pdf', minimalValidPdf()),
        'business_documents',
        $this->user,
    );

    expect($stored->status)->toBe('processing')
        ->and($stored->scan_status)->toBe('not_required');
    Queue::assertPushed(BuildDocumentPreview::class);

    app()->call([new BuildDocumentPreview($stored), 'handle']);

    $stored->refresh()->load('variants');
    $preview = $stored->variants->firstWhere('name', 'preview');
    expect($stored->status)->toBe('ready')
        ->and($preview)->not->toBeNull()
        ->and($preview->disk)->toBe('local')
        ->and($preview->mime_type)->toBe('image/jpeg');
    Storage::disk('local')->assertExists($preview->path);
    Storage::disk('public')->assertMissing($preview->path);

    $this->get(route('uploads.preview', $stored))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy');
});

test('pdf rusak ditolak dan canonical privat dihapus', function () {
    $stored = app(AsyncUploadService::class)->stage(
        UploadedFile::fake()->createWithContent('rusak.pdf', "%PDF-1.4\nrusak\n%%EOF"),
        'business_documents',
        $this->user,
    );
    $path = $stored->path;

    app()->call([new BuildDocumentPreview($stored), 'handle']);

    expect($stored->refresh()->status)->toBe('failed')
        ->and($stored->failure_code)->toBe('pdf_invalid')
        ->and($stored->path)->toBeNull()
        ->and($stored->variants()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
    $this->get(route('uploads.preview', $stored))->assertStatus(423);
});

test('output poppler terkait password dipetakan ke penolakan password protected', function () {
    $job = new BuildDocumentPreview(new StoredFile);
    $method = new ReflectionMethod($job, 'pdfFailureCode');

    expect($method->invoke($job, 'Command Line Error: Incorrect password'))->toBe('pdf_password_protected')
        ->and($method->invoke($job, 'Syntax Error: trailer not found'))->toBe('pdf_invalid');
});

test('token calendar import baru diantrekan setelah diklaim form domain', function () {
    $response = $this->postJson(route('uploads.store'), [
        'policy' => 'calendar_import',
        'file' => UploadedFile::fake()->createWithContent(
            'kalender.csv',
            "Tanggal,Keterangan\n2026-08-17,HUT RI\n",
        ),
    ])->assertAccepted()->assertJsonPath('status', 'processing');
    $stored = StoredFile::query()->where('uuid', $response->json('uuid'))->firstOrFail();
    Queue::assertNotPushed(ProcessCalendarImport::class);

    $this->post(route('hari-libur.import'), ['file_upload_uuid' => $stored->uuid])
        ->assertRedirect(route('hari-libur.index'))
        ->assertSessionHas('success');

    expect($stored->refresh()->owner->is($this->user->koperasi))->toBeTrue()
        ->and($stored->claimed_at)->not->toBeNull();
    Queue::assertPushed(ProcessCalendarImport::class, fn ($job) => $job->storedFile->is($stored));
});
