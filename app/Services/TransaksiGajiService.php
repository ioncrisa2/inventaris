<?php

namespace App\Services;

use App\Models\KomponenGaji;
use App\Models\TransaksiGaji;
use App\Repositories\AbsensiRepository;
use App\Repositories\KaryawanRepository;
use App\Repositories\KomponenGajiRepository;
use App\Repositories\TransaksiGajiRepository;
use App\Rules\Decimal15Two;
use App\Support\PenggajianCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransaksiGajiService
{
    public function __construct(
        private TransaksiGajiRepository $transaksiGajiRepository,
        private KomponenGajiRepository $komponenGajiRepository,
        private KaryawanRepository $karyawanRepository,
        private AbsensiRepository $absensiRepository,
    ) {}

    /**
     * @param  array{search?: ?string, bulan?: ?string, tahun?: ?string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->transaksiGajiRepository->paginate($filters);
    }

    /**
     * Hitung ulang seluruh baris komponen di backend (nilai/metode kiriman
     * client tidak pernah dipakai langsung sebagai nominal_hasil final) lalu
     * simpan header + detail transaksi gaji dalam satu transaction.
     */
    public function store(array $header, array $barisTerpilih): TransaksiGaji
    {
        return $this->simpanTransaksi($header, $barisTerpilih, null);
    }

    public function update(TransaksiGaji $transaksiGaji, array $header, array $barisTerpilih): TransaksiGaji
    {
        return $this->simpanTransaksi($header, $barisTerpilih, $transaksiGaji);
    }

    public function destroy(TransaksiGaji $transaksiGaji): void
    {
        $this->transaksiGajiRepository->delete($transaksiGaji);
    }

    private function simpanTransaksi(array $header, array $barisTerpilih, ?TransaksiGaji $transaksiGaji): TransaksiGaji
    {
        try {
            return DB::transaction(
                fn () => $this->simpanTransaksiTerkunci($header, $barisTerpilih, $transaksiGaji),
                3,
            );
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isPeriodUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'karyawan_id' => 'Transaksi gaji karyawan untuk periode ini sudah ada.',
            ]);
        }
    }

    private function simpanTransaksiTerkunci(
        array $header,
        array $barisTerpilih,
        ?TransaksiGaji $transaksiGaji,
    ): TransaksiGaji {
        $karyawanId = $transaksiGaji?->karyawan_id ?? (int) $header['karyawan_id'];
        $karyawan = $this->karyawanRepository->findOrFailForUpdate($karyawanId);

        if ($transaksiGaji) {
            $transaksiGaji = $this->transaksiGajiRepository->findOrFailForUpdate($transaksiGaji->id);
            // Karyawan pemilik transaksi tidak bisa diganti lewat edit, walau
            // client mengirim karyawan_id lain — kunci ke pemilik aslinya.
            $header['karyawan_id'] = $transaksiGaji->karyawan_id;
        }

        $bulan = (int) $header['bulan'];
        $tahun = (int) $header['tahun'];

        if ($this->transaksiGajiRepository->conflictingPeriodForUpdate(
            $karyawan->id,
            $bulan,
            $tahun,
            $transaksiGaji?->id,
        )) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Transaksi gaji karyawan untuk periode ini sudah ada.',
            ]);
        }

        $gajiPokok = Decimal15Two::normalizeNonNegative($karyawan->gaji_pokok);

        if ($gajiPokok === null) {
            throw ValidationException::withMessages([
                'karyawan_id' => 'Gaji pokok karyawan berada di luar batas nominal yang didukung.',
            ]);
        }

        $masterIds = $this->masterIds($barisTerpilih);
        $komponenById = $masterIds === []
            ? collect()
            : $this->komponenGajiRepository->findManyForUpdate($masterIds)->keyBy('id');
        $customIds = $this->customIds($barisTerpilih);
        $detailCustomById = $transaksiGaji && $customIds !== []
            ? $transaksiGaji->details()
                ->whereKey($customIds)
                ->whereNull('komponen_gaji_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
            : collect();
        $jumlahHadir = $this->absensiRepository->hitungHadir($karyawan->id, $bulan, $tahun);

        $totalTunjangan = '0.00';
        $totalPotongan = '0.00';
        $barisSiapSimpan = [];

        foreach ($barisTerpilih as $kunci => $row) {
            $kunci = (string) $kunci;
            $baris = $this->siapkanBaris(
                $kunci,
                $row,
                $gajiPokok,
                $jumlahHadir,
                $transaksiGaji,
                $komponenById,
                $detailCustomById,
            );

            if ($baris['jenis_snapshot'] === 'Tunjangan') {
                $totalTunjangan = bcadd($totalTunjangan, $baris['nominal_hasil'], 2);
            } else {
                $totalPotongan = bcadd($totalPotongan, $baris['nominal_hasil'], 2);
            }

            $barisSiapSimpan[] = $baris;
        }

        if ($barisSiapSimpan === []) {
            throw ValidationException::withMessages([
                'baris' => 'Pilih minimal satu komponen gaji yang masih valid untuk transaksi ini.',
            ]);
        }

        $gajiBersih = bcsub(bcadd($gajiPokok, $totalTunjangan, 2), $totalPotongan, 2);

        if (! Decimal15Two::fitsSigned($gajiBersih)) {
            throw ValidationException::withMessages([
                'baris' => 'Total gaji bersih berada di luar batas nominal yang didukung.',
            ]);
        }

        $data = [...$header, 'gaji_pokok' => $gajiPokok, 'gaji_bersih' => $gajiBersih];

        $transaksiGaji = $transaksiGaji
            ? $this->transaksiGajiRepository->update($transaksiGaji, $data)
            : $this->transaksiGajiRepository->create($data);

        $this->transaksiGajiRepository->replaceDetails($transaksiGaji, $barisSiapSimpan);

        return $transaksiGaji;
    }

    /**
     * @return array{komponen_gaji_id: ?int, nama_komponen_snapshot: string, jenis_snapshot: string, metode_perhitungan_snapshot: string, nilai_snapshot: string, dasar_persentase_snapshot: ?string, jumlah_hadir_snapshot: ?int, nominal_hasil: string}
     */
    private function siapkanBaris(
        string $kunci,
        array $row,
        string $gajiPokok,
        int $jumlahHadir,
        ?TransaksiGaji $transaksiGaji,
        Collection $komponenById,
        Collection $detailCustomById,
    ): array {
        if (preg_match('/\Amaster_([1-9]\d*)\z/', $kunci, $matches)) {
            $komponen = $komponenById->get((int) $matches[1]);

            if (! $komponen) {
                throw ValidationException::withMessages([
                    "baris.{$kunci}" => 'Komponen gaji yang dipilih sudah tidak tersedia.',
                ]);
            }

            $namaSnapshot = $komponen->nama_komponen;
            $jenisSnapshot = $komponen->jenis;
            $komponenGajiId = $komponen->id;
            // Baris master tidak boleh dikunci nilainya dari client: metode &
            // nilai selalu diambil dari Komponen Gaji saat ini, bukan dari $row.
            $metode = $komponen->metode_perhitungan;
            $nilai = (string) $komponen->nilai_default;
        } elseif (preg_match('/\Acustom_([1-9]\d*)\z/', $kunci, $matches) && $transaksiGaji) {
            $detailYatim = $detailCustomById->get((int) $matches[1]);

            if (! $detailYatim) {
                throw ValidationException::withMessages([
                    "baris.{$kunci}" => 'Komponen khusus bukan milik transaksi ini.',
                ]);
            }

            $namaSnapshot = $detailYatim->nama_komponen_snapshot;
            $jenisSnapshot = $detailYatim->jenis_snapshot;
            $komponenGajiId = null;
            $metode = $row['metode_perhitungan'] ?? null;
            $nilai = Decimal15Two::normalizeNonNegative($row['nilai'] ?? null);

            if (! in_array($metode, array_keys(KomponenGaji::METODE_PERHITUNGAN), true)
                || $nilai === null
                || ($metode === 'persentase' && bccomp($nilai, '100', 2) > 0)) {
                throw ValidationException::withMessages([
                    "baris.{$kunci}" => 'Nilai atau metode komponen khusus tidak valid.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                "baris.{$kunci}" => 'Format komponen gaji tidak valid.',
            ]);
        }

        $nilai = Decimal15Two::normalizeNonNegative($nilai);

        if ($nilai === null) {
            throw ValidationException::withMessages([
                "baris.{$kunci}.nilai" => 'Nilai komponen berada di luar batas nominal yang didukung.',
            ]);
        }

        $dasarSnapshot = $metode === 'persentase' ? 'gaji_pokok' : null;
        $jumlahHadirSnapshot = $metode === 'per_kehadiran' ? $jumlahHadir : null;
        $nominalHasil = PenggajianCalculator::hitungNominal($metode, $nilai, $gajiPokok, $jumlahHadirSnapshot);

        if (Decimal15Two::normalizeNonNegative($nominalHasil) === null) {
            throw ValidationException::withMessages([
                "baris.{$kunci}.nilai" => 'Hasil perhitungan komponen berada di luar batas nominal yang didukung.',
            ]);
        }

        return [
            'komponen_gaji_id' => $komponenGajiId,
            'nama_komponen_snapshot' => $namaSnapshot,
            'jenis_snapshot' => $jenisSnapshot,
            'metode_perhitungan_snapshot' => $metode,
            'nilai_snapshot' => $nilai,
            'dasar_persentase_snapshot' => $dasarSnapshot,
            'jumlah_hadir_snapshot' => $jumlahHadirSnapshot,
            'nominal_hasil' => $nominalHasil,
        ];
    }

    private function masterIds(array $barisTerpilih): array
    {
        $ids = [];

        foreach (array_keys($barisTerpilih) as $kunci) {
            if (preg_match('/\Amaster_([1-9]\d*)\z/', (string) $kunci, $matches)) {
                $ids[] = (int) $matches[1];
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /** @return list<int> */
    private function customIds(array $barisTerpilih): array
    {
        $ids = [];

        foreach (array_keys($barisTerpilih) as $kunci) {
            if (preg_match('/\Acustom_([1-9]\d*)\z/', (string) $kunci, $matches)) {
                $ids[] = (int) $matches[1];
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function isPeriodUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        if ($exception->index === 'transaksi_gaji_karyawan_id_bulan_tahun_unique') {
            return true;
        }

        $columns = $exception->columns;
        sort($columns);

        return $columns === ['bulan', 'karyawan_id', 'tahun'];
    }

    /**
     * Siapkan data baris komponen untuk form (create & edit), termasuk
     * mengisi ulang dari old() saat validasi gagal supaya isian pengguna
     * tidak hilang. View cukup me-render array ini tanpa logika tambahan.
     *
     * @return array{0: array, 1: array} [$barisMaster, $barisYatim]
     */
    public function formData(?TransaksiGaji $transaksiGaji): array
    {
        $komponenGajis = $this->komponenGajiRepository->orderedList();
        $detailByKunci = $transaksiGaji
            ? $transaksiGaji->details->keyBy(fn ($detail) => $detail->komponen_gaji_id ? "master_{$detail->komponen_gaji_id}" : "custom_{$detail->id}")
            : collect();
        $oldBaris = old('baris');

        $barisMaster = $komponenGajis->map(function ($komponen) use ($detailByKunci, $oldBaris) {
            $kunci = "master_{$komponen->id}";
            $detail = $detailByKunci->get($kunci);

            return [
                'kunci' => $kunci,
                'nama_komponen' => $komponen->nama_komponen,
                'jenis' => $komponen->jenis,
                'checked' => $oldBaris !== null ? data_get($oldBaris, "{$kunci}.pakai") !== null : (bool) $detail,
                // metode & nilai baris master TIDAK bisa diubah per transaksi — selalu
                // ikut nilai_default/metode_perhitungan Komponen Gaji saat ini. Untuk
                // mengubahnya, edit master di halaman Komponen Gaji.
                'metode' => $komponen->metode_perhitungan,
                'nilai' => (string) $komponen->nilai_default,
            ];
        })->all();

        $idKomponenAktif = $komponenGajis->pluck('id');
        $barisYatim = $transaksiGaji
            ? $transaksiGaji->details
                ->whereNotIn('komponen_gaji_id', $idKomponenAktif)
                ->map(function ($detail) use ($oldBaris) {
                    $kunci = "custom_{$detail->id}";

                    return [
                        'kunci' => $kunci,
                        'nama_komponen' => $detail->nama_komponen_snapshot,
                        'jenis' => $detail->jenis_snapshot,
                        'checked' => $oldBaris !== null ? data_get($oldBaris, "{$kunci}.pakai") !== null : true,
                        'metode' => data_get($oldBaris, "{$kunci}.metode_perhitungan") ?? $detail->metode_perhitungan_snapshot,
                        'nilai' => data_get($oldBaris, "{$kunci}.nilai") ?? $detail->nilai_snapshot,
                    ];
                })
                ->values()
                ->all()
            : [];

        return [$barisMaster, $barisYatim];
    }

    public function totalPerJenis(TransaksiGaji $transaksiGaji, string $jenis): string
    {
        return $transaksiGaji->details
            ->where('jenis_snapshot', $jenis)
            ->reduce(fn ($total, $detail) => bcadd($total, (string) $detail->nominal_hasil, 2), '0.00');
    }
}
