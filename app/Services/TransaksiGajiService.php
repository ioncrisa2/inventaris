<?php

namespace App\Services;

use App\Models\KomponenGaji;
use App\Models\TransaksiGaji;
use App\Repositories\KaryawanRepository;
use App\Repositories\KomponenGajiRepository;
use App\Repositories\TransaksiGajiRepository;
use App\Rules\Decimal15Two;
use App\Support\PenggajianCalculator;
use App\Support\PerPage;
use App\Support\SlipGajiPaperLayout;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransaksiGajiService
{
    private const MAX_BULK_PRINT_SLIPS = 100;

    public function __construct(
        private TransaksiGajiRepository $transaksiGajiRepository,
        private KomponenGajiRepository $komponenGajiRepository,
        private KaryawanRepository $karyawanRepository,
        private HariOperasionalService $hariOperasionalService,
        private GajiKaryawanResolver $gajiKaryawanResolver,
    ) {}

    public function karyawanList(?string $search, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->transaksiGajiRepository->karyawanList($search, $perPage);
    }

    /**
     * @param  array{bulan?: ?string, tahun?: ?string}  $filters
     */
    public function listForKaryawan(int $karyawanId, array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->transaksiGajiRepository->paginateForKaryawan($karyawanId, $filters, $perPage);
    }

    /**
     * @param  array<string, int|string>  $filter
     * @return Collection<int, TransaksiGaji>
     */
    public function slipsForBulkPrint(array $filter): Collection
    {
        $limit = self::MAX_BULK_PRINT_SLIPS + 1;

        $transaksiGaji = $filter['mode'] === 'periode'
            ? $this->transaksiGajiRepository->forSlipPrintPeriod(
                (int) $filter['bulan'],
                (int) $filter['tahun'],
                $limit,
            )
            : $this->transaksiGajiRepository->forSlipPrintEmployeeRange(
                (int) $filter['karyawan_id'],
                (int) $filter['tahun_awal'],
                (int) $filter['bulan_awal'],
                (int) $filter['tahun_akhir'],
                (int) $filter['bulan_akhir'],
                $limit,
            );

        if ($transaksiGaji->isEmpty()) {
            throw ValidationException::withMessages([
                'mode' => 'Tidak ada transaksi gaji yang cocok dengan filter cetak.',
            ]);
        }

        if ($transaksiGaji->count() > self::MAX_BULK_PRINT_SLIPS) {
            throw ValidationException::withMessages([
                'mode' => 'Hasil filter melebihi 100 slip. Persempit periode cetak.',
            ]);
        }

        return $transaksiGaji;
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

        try {
            $tanggalAcuanGaji = Carbon::create($tahun, $bulan, 1)->endOfMonth();
            $gajiPokok = $this->gajiKaryawanResolver->berlakuPada($karyawan, $tanggalAcuanGaji);
        } catch (\DomainException) {
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
        $totalTunjangan = '0.00';
        $totalPotongan = '0.00';
        $barisSiapSimpan = [];

        foreach ($barisTerpilih as $kunci => $row) {
            $kunci = (string) $kunci;
            $baris = $this->siapkanBaris(
                $kunci,
                $row,
                $gajiPokok,
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
     * @return array{komponen_gaji_id: ?int, nama_komponen_snapshot: string, jenis_snapshot: string, metode_perhitungan_snapshot: string, nilai_snapshot: string, dasar_persentase_snapshot: ?string, tanggal_awal_snapshot: ?string, tanggal_akhir_snapshot: ?string, jumlah_hari_snapshot: ?int, nominal_hasil: string}
     */
    private function siapkanBaris(
        string $kunci,
        array $row,
        string $gajiPokok,
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
            // nilai selalu diambil dari Komponen Gaji saat ini, bukan dari
            // $row. Pengecualian: rentang tanggal untuk metode per_hari
            // memang input per-transaksi dan hanya tersedia dari $row.
            $metode = $komponen->metode_perhitungan;
            $nilai = (string) $komponen->nilai_default;
            $tanggalAwal = $row['tanggal_awal'] ?? null;
            $tanggalAkhir = $row['tanggal_akhir'] ?? null;
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
            $tanggalAwal = $row['tanggal_awal'] ?? null;
            $tanggalAkhir = $row['tanggal_akhir'] ?? null;

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
        $tanggalAwalSnapshot = null;
        $tanggalAkhirSnapshot = null;
        $jumlahHariSnapshot = null;

        if ($metode === 'per_hari') {
            try {
                $awalCarbon = Carbon::parse($tanggalAwal)->startOfDay();
                $akhirCarbon = Carbon::parse($tanggalAkhir)->startOfDay();
            } catch (\Exception) {
                throw ValidationException::withMessages([
                    "baris.{$kunci}.tanggal_awal" => 'Rentang tanggal tidak valid.',
                ]);
            }

            if ($akhirCarbon->lt($awalCarbon)) {
                throw ValidationException::withMessages([
                    "baris.{$kunci}.tanggal_akhir" => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
                ]);
            }

            $tanggalAwalSnapshot = $awalCarbon->toDateString();
            $tanggalAkhirSnapshot = $akhirCarbon->toDateString();
            $jumlahHariSnapshot = $this->hariOperasionalService->jumlahHariOperasional($awalCarbon, $akhirCarbon);
        }

        $nominalHasil = PenggajianCalculator::hitungNominal($metode, $nilai, $gajiPokok, $jumlahHariSnapshot);

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
            'tanggal_awal_snapshot' => $tanggalAwalSnapshot,
            'tanggal_akhir_snapshot' => $tanggalAkhirSnapshot,
            'jumlah_hari_snapshot' => $jumlahHariSnapshot,
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
                'tanggal_awal' => $oldBaris !== null
                    ? data_get($oldBaris, "{$kunci}.tanggal_awal")
                    : $detail?->tanggal_awal_snapshot?->format('Y-m-d'),
                'tanggal_akhir' => $oldBaris !== null
                    ? data_get($oldBaris, "{$kunci}.tanggal_akhir")
                    : $detail?->tanggal_akhir_snapshot?->format('Y-m-d'),
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
                        'tanggal_awal' => data_get($oldBaris, "{$kunci}.tanggal_awal") ?? $detail->tanggal_awal_snapshot?->format('Y-m-d'),
                        'tanggal_akhir' => data_get($oldBaris, "{$kunci}.tanggal_akhir") ?? $detail->tanggal_akhir_snapshot?->format('Y-m-d'),
                    ];
                })
                ->values()
                ->all()
            : [];

        return [$barisMaster, $barisYatim];
    }

    /**
     * Siapkan halaman cetak slip. Slip dengan rincian panjang berdiri sendiri;
     * slip normal dipasangkan maksimal dua per lembar F4 portrait.
     *
     * @param  Collection<int, TransaksiGaji>  $transaksiGaji
     * @return Collection<int, Collection<int, array{
     *     transaksi: TransaksiGaji,
     *     tunjangan: Collection,
     *     potongan: Collection,
     *     total_tunjangan: string,
     *     total_potongan: string,
     *     is_full_page: bool
     * }>>
     */
    public function slipPrintPages(
        Collection $transaksiGaji,
        string $paperLayout = SlipGajiPaperLayout::DEFAULT,
    ): Collection {
        $paperLayout = SlipGajiPaperLayout::normalize($paperLayout);

        $slips = $transaksiGaji->map(function (TransaksiGaji $transaksi) use ($paperLayout): array {
            $tunjangan = $transaksi->details
                ->where('jenis_snapshot', 'Tunjangan')
                ->values();
            $potongan = $transaksi->details
                ->where('jenis_snapshot', 'Potongan')
                ->values();

            return [
                'transaksi' => $transaksi,
                'tunjangan' => $tunjangan,
                'potongan' => $potongan,
                'total_tunjangan' => $tunjangan->reduce(
                    fn ($total, $detail) => bcadd($total, (string) $detail->nominal_hasil, 2),
                    '0.00',
                ),
                'total_potongan' => $potongan->reduce(
                    fn ($total, $detail) => bcadd($total, (string) $detail->nominal_hasil, 2),
                    '0.00',
                ),
                'is_full_page' => $this->slipRequiresFullPage($tunjangan, $potongan, $paperLayout),
            ];
        });

        $pages = collect();
        $currentPage = collect();

        foreach ($slips as $slip) {
            if ($slip['is_full_page']) {
                if ($currentPage->isNotEmpty()) {
                    $pages->push($currentPage);
                    $currentPage = collect();
                }

                $pages->push(collect([$slip]));

                continue;
            }

            $currentPage->push($slip);

            if ($currentPage->count() === 2) {
                $pages->push($currentPage);
                $currentPage = collect();
            }
        }

        if ($currentPage->isNotEmpty()) {
            $pages->push($currentPage);
        }

        return $pages;
    }

    private function slipRequiresFullPage(
        Collection $tunjangan,
        Collection $potongan,
        string $paperLayout,
    ): bool {
        $charactersPerRow = $paperLayout === SlipGajiPaperLayout::LEFT_RIGHT ? 19 : 28;
        $estimatedRows = $tunjangan
            ->merge($potongan)
            ->sum(fn ($detail) => max(
                1,
                (int) ceil(mb_strlen($detail->nama_komponen_snapshot) / $charactersPerRow),
            ));

        if ($paperLayout === SlipGajiPaperLayout::LEFT_RIGHT) {
            return $estimatedRows > 20
                || $tunjangan->count() + $potongan->count() > 18
                || max($tunjangan->count(), $potongan->count()) > 14;
        }

        return $estimatedRows > 12
            || $tunjangan->count() + $potongan->count() > 12
            || max($tunjangan->count(), $potongan->count()) > 8;
    }

    public function totalPerJenis(TransaksiGaji $transaksiGaji, string $jenis): string
    {
        return $transaksiGaji->details
            ->where('jenis_snapshot', $jenis)
            ->reduce(fn ($total, $detail) => bcadd($total, (string) $detail->nominal_hasil, 2), '0.00');
    }
}
