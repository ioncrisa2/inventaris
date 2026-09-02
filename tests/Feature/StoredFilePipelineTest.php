<?php

use App\Models\Barang;
use App\Models\StoredFile;
use App\Models\UnitKerja;
use App\Services\MediaBackfillService;
use App\Services\StoredFileService;
use App\Services\TransactionalFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'Media', 'kode' => 'MED']);
    $this->barang = Barang::create([
        'kode_barang' => 'MED-001',
        'nama_barang' => 'Barang Media',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay(),
        'harga_perolehan' => 1000000,
    ]);
    Storage::fake('public');
    Storage::fake('local');
});

test('foto karyawan dinormalisasi ke master dan thumbnail webp tanpa menyimpan raw', function () {
    $service = app(StoredFileService::class);
    $prepared = $service->prepare(UploadedFile::fake()->image('pegawai.jpg', 1200, 800), 'employee_photo');

    try {
        expect($prepared->variants)->toHaveKeys(['canonical', 'thumbnail'])
            ->and($prepared->variants['canonical']['mime_type'])->toBe('image/webp')
            ->and($prepared->variants['canonical']['width'])->toBe(640)
            ->and($prepared->variants['canonical']['height'])->toBe(640)
            ->and($prepared->variants['thumbnail']['width'])->toBe(160)
            ->and($prepared->variants['thumbnail']['height'])->toBe(160);

        $registry = app(TransactionalFileStorage::class)->transaction(fn () => $service->persist(
            $prepared,
            (int) $this->barang->koperasi_id,
            'foto_karyawan',
            $this->barang,
            auth()->id(),
        ));

        expect($registry->path)
            ->toStartWith('tenants/'.$this->barang->koperasi_id.'/employee_photo/')
            ->toEndWith('.webp')
            ->and($registry->variants()->count())->toBe(2)
            ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
        foreach (Storage::disk('public')->allFiles() as $path) {
            expect($path)->toEndWith('.webp');
        }
    } finally {
        $prepared->cleanup();
    }
});

test('dokumen disimpan privat dan byte canonical identik dengan sumber', function () {
    $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
    $prepared = app(StoredFileService::class)->prepare(
        UploadedFile::fake()->createWithContent('nota.pdf', $bytes),
        'business_documents',
    );

    try {
        $registry = app(TransactionalFileStorage::class)->transaction(fn () => app(StoredFileService::class)->persist(
            $prepared,
            (int) $this->barang->koperasi_id,
            'dokumen',
            $this->barang,
            auth()->id(),
        ));

        expect($registry->disk)->toBe('local')
            ->and($registry->source_checksum_sha256)->toBe(hash('sha256', $bytes))
            ->and($registry->final_checksum_sha256)->toBe(hash('sha256', $bytes))
            ->and(Storage::disk('local')->get($registry->path))->toBe($bytes);
        Storage::disk('public')->assertMissing($registry->path);
    } finally {
        $prepared->cleanup();
    }
});

test('rollback database membersihkan file dan varian registry baru', function () {
    $prepared = app(StoredFileService::class)->prepare(
        UploadedFile::fake()->image('rollback.jpg', 900, 600),
        'asset_photo',
    );

    try {
        expect(fn () => DB::transaction(function () use ($prepared) {
            app(StoredFileService::class)->persist(
                $prepared,
                (int) $this->barang->koperasi_id,
                'foto_sampul',
                $this->barang,
                auth()->id(),
            );

            throw new RuntimeException('Paksa rollback.');
        }))->toThrow(RuntimeException::class);

        expect(StoredFile::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    } finally {
        $prepared->cleanup();
    }
});

test('backfill legacy mendukung dry run dan dapat dijalankan ulang tanpa duplikasi', function () {
    $legacyPath = 'barang-sampul/legacy.jpg';
    Storage::disk('public')->put($legacyPath, 'legacy-content');
    $this->barang->update(['foto_sampul' => $legacyPath]);

    $backfill = app(MediaBackfillService::class);
    $dryRun = $backfill->run(dryRun: true, chunk: 1);
    expect($dryRun['created'])->toBe(1)
        ->and(StoredFile::query()->count())->toBe(0);

    $first = $backfill->run(chunk: 1);
    expect($first['created'])->toBe(1)
        ->and($first['existing'])->toBe(0)
        ->and(StoredFile::query()->count())->toBe(1);

    $second = $backfill->run(chunk: 1);
    expect($second['created'])->toBe(0)
        ->and($second['existing'])->toBe(1)
        ->and(StoredFile::query()->count())->toBe(1)
        ->and($this->barang->storedFiles()->first()->final_checksum_sha256)
        ->toBe(hash('sha256', 'legacy-content'));
});

test('backfill melaporkan file legacy yang hilang tanpa membuat registry', function () {
    $this->barang->update(['foto_sampul' => 'barang-sampul/hilang.jpg']);

    $stats = app(MediaBackfillService::class)->run(chunk: 1);

    expect($stats['missing'])->toBe(1)
        ->and($stats['created'])->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0);
});
