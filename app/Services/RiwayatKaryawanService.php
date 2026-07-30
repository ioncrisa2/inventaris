<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatKaryawan;
use App\Models\RiwayatKaryawanPerubahan;
use App\Models\User;
use App\Repositories\KaryawanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Rules\Decimal15Two;
use App\Support\KaryawanPerubahanSchema;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiwayatKaryawanService
{
    public function __construct(
        private KaryawanRepository $karyawanRepository,
        private UnitKerjaRepository $unitKerjaRepository,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    public function catat(Karyawan $karyawan, User $pelaku, array $data): RiwayatKaryawan
    {
        return DB::transaction(function () use ($karyawan, $pelaku, $data) {
            $karyawanTerkunci = $this->karyawanRepository->findOrFailForUpdate($karyawan->id);
            $jenis = $data['jenis_perubahan'];
            $schema = KaryawanPerubahanSchema::types()[$jenis];
            $fields = $schema['fields'];
            $fotoLama = null;

            if (($data['foto_karyawan'] ?? null) instanceof UploadedFile) {
                $fotoLama = $karyawanTerkunci->foto_karyawan;
                $data['foto_karyawan'] = $this->fileStorage->store(
                    'public',
                    'karyawan-foto',
                    $data['foto_karyawan'],
                );
            } else {
                $fields = array_values(array_filter(
                    $fields,
                    fn (string $field) => $field !== 'foto_karyawan',
                ));
            }

            $nilaiBaru = collect($fields)
                ->mapWithKeys(fn (string $field) => [
                    $field => $this->normalisasiNilai($field, $data[$field] ?? null),
                ])
                ->all();

            $perubahan = $this->buatPerubahan(
                $karyawanTerkunci,
                $fields,
                fn (string $field) => $karyawanTerkunci->getAttribute($field),
                fn (string $field) => $nilaiBaru[$field] ?? null,
            );

            if ($perubahan === []) {
                throw ValidationException::withMessages([
                    'jenis_perubahan' => 'Tidak ada nilai yang berubah. Periksa kembali data yang Anda masukkan.',
                ]);
            }

            $this->pastikanUrutanKronologis(
                $karyawanTerkunci,
                array_column($perubahan, 'field'),
                $data['tanggal_berlaku'],
            );

            $karyawanTerkunci = $this->karyawanRepository->update($karyawanTerkunci, $nilaiBaru);
            $riwayat = $this->simpanRiwayat(
                $karyawanTerkunci,
                $pelaku,
                $jenis,
                $data['tanggal_berlaku'],
                $data['alasan'],
                'aksi_perubahan',
                $perubahan,
            );

            $this->simpanDokumen($riwayat, $data['dokumen_pendukung'] ?? []);
            $this->fileStorage->deleteAfterCommit('public', $fotoLama);
            $this->dashboardCache->invalidateAfterCommit();

            return $riwayat->load(['perubahan', 'dokumen']);
        }, 3);
    }

    /**
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function buatPerubahan(
        Karyawan $karyawan,
        array $fields,
        callable $nilaiLama,
        callable $nilaiBaru,
    ): array {
        $labels = KaryawanPerubahanSchema::fields();
        $sensitif = KaryawanPerubahanSchema::sensitiveFields();
        $perubahan = [];

        foreach ($fields as $field) {
            $lama = $this->normalisasiNilai($field, $nilaiLama($field));
            $baru = $this->normalisasiNilai($field, $nilaiBaru($field));

            if ($this->nilaiSama($lama, $baru)) {
                continue;
            }

            $perubahan[] = [
                'field' => $field,
                'label' => $labels[$field] ?? $field,
                'nilai_lama' => $this->nilaiUntukAudit($lama),
                'nilai_baru' => $this->nilaiUntukAudit($baru),
                'tampilan_lama' => $this->tampilanNilai($karyawan, $field, $lama),
                'tampilan_baru' => $this->tampilanNilai($karyawan, $field, $baru),
                'sensitif' => in_array($field, $sensitif, true),
            ];
        }

        return $perubahan;
    }

    private function simpanRiwayat(
        Karyawan $karyawan,
        User $pelaku,
        string $jenis,
        string $tanggalBerlaku,
        string $alasan,
        string $sumber,
        array $perubahan,
    ): RiwayatKaryawan {
        $riwayat = RiwayatKaryawan::create([
            'karyawan_id' => $karyawan->id,
            'user_id' => $pelaku->id,
            'nama_pelaku_snapshot' => $pelaku->name,
            'jenis_perubahan' => $jenis,
            'tanggal_berlaku' => $tanggalBerlaku,
            'alasan' => trim($alasan),
            'sumber' => $sumber,
        ]);

        $riwayat->perubahan()->createMany($perubahan);

        return $riwayat;
    }

    /**
     * Audit trail harus ditambahkan berurutan. Menerima perubahan yang
     * dibackdate sebelum perubahan terakhir akan memutus rantai nilai
     * lama/baru dan membuat master tidak lagi mewakili kondisi terkini.
     *
     * @param  list<string>  $fields
     */
    private function pastikanUrutanKronologis(
        Karyawan $karyawan,
        array $fields,
        string $tanggalBerlaku,
    ): void {
        $tanggalTerakhir = RiwayatKaryawanPerubahan::query()
            ->whereIn('field', $fields)
            ->whereHas('riwayat', fn ($query) => $query->where('karyawan_id', $karyawan->id))
            ->join('riwayat_karyawan', 'riwayat_karyawan.id', '=', 'riwayat_karyawan_perubahan.riwayat_karyawan_id')
            ->max('riwayat_karyawan.tanggal_berlaku');

        if ($tanggalTerakhir !== null && $tanggalBerlaku < $tanggalTerakhir) {
            throw ValidationException::withMessages([
                'tanggal_berlaku' => 'Tanggal berlaku tidak boleh mendahului histori terakhir untuk data yang diubah ('
                    .date('d/m/Y', strtotime($tanggalTerakhir)).').',
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $dokumen
     */
    private function simpanDokumen(RiwayatKaryawan $riwayat, array $dokumen): void
    {
        foreach ($dokumen as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $realPath = $file->getRealPath();
            $checksum = is_string($realPath) ? hash_file('sha256', $realPath) : null;
            $path = $this->fileStorage->store('local', 'riwayat-karyawan', $file);

            $riwayat->dokumen()->create([
                'nama_asli' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'ukuran' => $file->getSize(),
                'checksum_sha256' => $checksum ?: null,
            ]);
        }
    }

    private function normalisasiNilai(string $field, mixed $nilai): mixed
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if ($nilai instanceof CarbonInterface) {
            return $nilai->toDateString();
        }

        if ($field === 'gaji_pokok') {
            $normal = Decimal15Two::normalizeNonNegative($nilai);

            return $normal === null ? null : bcadd($normal, '0', 2);
        }

        if (in_array($field, ['unit_kerja_id', 'atasan_langsung_id', 'tahun_lulus', 'jumlah_anak'], true)) {
            return (int) $nilai;
        }

        if (in_array($field, [
            'tanggal_lahir',
            'tanggal_masuk_kerja',
            'tanggal_sk_pengangkatan',
            'tanggal_mengundurkan_diri',
        ], true)) {
            return (string) $nilai;
        }

        return is_string($nilai) ? trim($nilai) : $nilai;
    }

    private function nilaiSama(mixed $nilaiLama, mixed $nilaiBaru): bool
    {
        if ($nilaiLama === null && $nilaiBaru === null) {
            return true;
        }

        return (string) $nilaiLama === (string) $nilaiBaru;
    }

    private function nilaiUntukAudit(mixed $nilai): ?string
    {
        return $nilai === null ? null : (string) $nilai;
    }

    private function tampilanNilai(Karyawan $karyawan, string $field, mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '—';
        }

        if ($field === 'unit_kerja_id') {
            return $this->unitKerjaRepository->find((int) $nilai)?->nama_unit ?? 'Unit tidak tersedia';
        }

        if ($field === 'atasan_langsung_id') {
            return $this->karyawanRepository->find((int) $nilai)?->nama_lengkap ?? 'Karyawan tidak tersedia';
        }

        if ($field === 'gaji_pokok') {
            return 'Rp '.number_format((float) $nilai, 0, ',', '.');
        }

        if ($field === 'foto_karyawan') {
            return 'Foto tersimpan';
        }

        if (str_starts_with($field, 'tanggal_')) {
            return date('d/m/Y', strtotime((string) $nilai));
        }

        return (string) $nilai;
    }
}
