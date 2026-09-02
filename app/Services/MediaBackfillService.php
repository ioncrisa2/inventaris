<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\Pengaturan;
use App\Models\ProductRequestAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaBackfillService
{
    public function __construct(private StoredFileService $storedFiles) {}

    public function countCandidates(): int
    {
        return array_sum(array_map(
            fn (array $source): int => (int) $source['query']()->count(),
            $this->sources(),
        ));
    }

    /**
     * @param  null|callable(string):void  $progress
     * @return array{scanned:int,created:int,existing:int,missing:int,unreadable:int,invalid:int,details:list<string>}
     */
    public function run(bool $dryRun = false, int $chunk = 200, ?callable $progress = null): array
    {
        $stats = [
            'scanned' => 0,
            'created' => 0,
            'existing' => 0,
            'missing' => 0,
            'unreadable' => 0,
            'invalid' => 0,
            'details' => [],
        ];

        foreach ($this->sources() as $source) {
            $source['query']()->chunkById(max(1, $chunk), function ($records) use (
                $source,
                $dryRun,
                $progress,
                &$stats,
            ): void {
                foreach ($records as $record) {
                    $stats['scanned']++;
                    $progress?->__invoke($source['label']);
                    $candidate = $source['map']($record);

                    if (! is_array($candidate) || ! ($candidate['owner'] ?? null) instanceof Model) {
                        $stats['invalid']++;
                        $this->detail($stats, $source['label'].': owner/tenant tidak tersedia untuk ID '.$record->getKey());

                        continue;
                    }

                    $existing = $candidate['owner']->storedFiles()
                        ->where('collection', $candidate['collection'])
                        ->where('disk', $candidate['disk'])
                        ->where('path', $candidate['path'])
                        ->exists();
                    if ($existing) {
                        $stats['existing']++;

                        continue;
                    }

                    $filesystem = Storage::disk($candidate['disk']);
                    if (! $filesystem->exists($candidate['path'])) {
                        $stats['missing']++;
                        $this->detail($stats, $source['label'].': file tidak ditemukan untuk ID '.$record->getKey());

                        continue;
                    }

                    if ($dryRun) {
                        $stream = null;
                        try {
                            $stream = $filesystem->readStream($candidate['path']);
                            if (! is_resource($stream)) {
                                throw new \RuntimeException('stream tidak tersedia');
                            }
                            $stats['created']++;
                        } catch (Throwable) {
                            $stats['unreadable']++;
                            $this->detail($stats, $source['label'].': file tidak dapat dibaca untuk ID '.$record->getKey());
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        continue;
                    }

                    try {
                        $this->storedFiles->registerLegacy(...$candidate);
                        $stats['created']++;
                    } catch (Throwable $exception) {
                        $stats['unreadable']++;
                        $this->detail(
                            $stats,
                            $source['label'].': gagal membaca ID '.$record->getKey().' ('.$exception->getMessage().')',
                        );
                    }
                }
            }, column: 'id');
        }

        return $stats;
    }

    /** @return list<array{label:string,query:callable():Builder,map:callable(Model):?array}> */
    private function sources(): array
    {
        return [
            [
                'label' => 'Foto sampul barang',
                'query' => fn () => Barang::withoutGlobalScopes()->whereNotNull('foto_sampul')->where('foto_sampul', '<>', ''),
                'map' => fn (Barang $barang) => $this->candidate(
                    $barang,
                    (int) $barang->koperasi_id,
                    'asset_photo',
                    'foto_sampul',
                    'public',
                    $barang->foto_sampul,
                ),
            ],
            [
                'label' => 'Galeri barang',
                'query' => fn () => FotoBarang::query()->with('barang:id,koperasi_id')->whereNotNull('path')->where('path', '<>', ''),
                'map' => fn (FotoBarang $foto) => $foto->barang
                    ? $this->candidate($foto, (int) $foto->barang->koperasi_id, 'asset_gallery', 'foto_pendukung', 'public', $foto->path)
                    : null,
            ],
            [
                'label' => 'Dokumen barang',
                'query' => fn () => DokumenBarang::query()->with('barang:id,koperasi_id')->whereNotNull('path')->where('path', '<>', ''),
                'map' => fn (DokumenBarang $dokumen) => $dokumen->barang
                    ? $this->candidate($dokumen, (int) $dokumen->barang->koperasi_id, 'business_documents', 'dokumen', 'local', $dokumen->path, $dokumen->nama_asli)
                    : null,
            ],
            [
                'label' => 'Foto karyawan',
                'query' => fn () => Karyawan::withoutGlobalScopes()->whereNotNull('foto_karyawan')->where('foto_karyawan', '<>', ''),
                'map' => fn (Karyawan $karyawan) => $this->candidate(
                    $karyawan,
                    (int) $karyawan->koperasi_id,
                    'employee_photo',
                    'foto_karyawan',
                    'public',
                    $karyawan->foto_karyawan,
                ),
            ],
            [
                'label' => 'Dokumen karyawan',
                'query' => fn () => DokumenKaryawan::query()->with('karyawan:id,koperasi_id')->whereNotNull('path')->where('path', '<>', ''),
                'map' => fn (DokumenKaryawan $dokumen) => $dokumen->karyawan
                    ? $this->candidate($dokumen, (int) $dokumen->karyawan->koperasi_id, 'business_documents', 'dokumen', 'local', $dokumen->path, $dokumen->nama_asli)
                    : null,
            ],
            [
                'label' => 'Dokumen histori karyawan',
                'query' => fn () => DokumenRiwayatKaryawan::query()
                    ->with('riwayat.karyawan:id,koperasi_id')
                    ->whereNotNull('path')
                    ->where('path', '<>', ''),
                'map' => fn (DokumenRiwayatKaryawan $dokumen) => $dokumen->riwayat?->karyawan
                    ? $this->candidate($dokumen, (int) $dokumen->riwayat->karyawan->koperasi_id, 'business_documents', 'dokumen', 'local', $dokumen->path, $dokumen->nama_asli)
                    : null,
            ],
            [
                'label' => 'Logo koperasi',
                'query' => fn () => Pengaturan::withoutGlobalScopes()
                    ->where('key', 'identitas_logo_path')
                    ->whereNotNull('value')
                    ->where('value', '<>', ''),
                'map' => function (Pengaturan $pengaturan) {
                    $koperasi = Koperasi::query()->find($pengaturan->koperasi_id);

                    return $koperasi
                        ? $this->candidate($koperasi, (int) $koperasi->id, 'logo', 'logo', 'public', $pengaturan->value)
                        : null;
                },
            ],
            [
                'label' => 'Lampiran request produk',
                'query' => fn () => ProductRequestAttachment::query()
                    ->with('productRequest:id,koperasi_id')
                    ->whereNotNull('path')
                    ->where('path', '<>', ''),
                'map' => fn (ProductRequestAttachment $attachment) => $attachment->productRequest
                    ? $this->candidate(
                        $attachment,
                        (int) $attachment->productRequest->koperasi_id,
                        'product_attachments',
                        'attachment',
                        $attachment->disk,
                        $attachment->path,
                        $attachment->original_name,
                        $attachment->uploaded_by,
                    )
                    : null,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function candidate(
        Model $owner,
        int $koperasiId,
        string $policy,
        string $collection,
        string $disk,
        string $path,
        ?string $originalName = null,
        ?int $uploadedBy = null,
    ): array {
        return compact(
            'owner',
            'koperasiId',
            'policy',
            'collection',
            'disk',
            'path',
            'originalName',
            'uploadedBy',
        );
    }

    /** @param array{details:list<string>} $stats */
    private function detail(array &$stats, string $message): void
    {
        if (count($stats['details']) < 100) {
            $stats['details'][] = $message;
        }
    }
}
