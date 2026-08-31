<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\TransaksiGaji\CetakSlipGajiMassalRequest;
use App\Http\Requests\TransaksiGaji\CetakSlipGajiRequest;
use App\Http\Requests\TransaksiGaji\StoreTransaksiGajiRequest;
use App\Http\Requests\TransaksiGaji\UpdateTransaksiGajiRequest;
use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use App\Repositories\KaryawanRepository;
use App\Services\PlatformFeatureService;
use App\Services\SlipGajiTemplateService;
use App\Services\TransaksiGajiService;
use App\Support\PerPage;
use App\Support\SlipGajiPaperLayout;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransaksiGajiController extends Controller
{
    public function __construct(
        private TransaksiGajiService $transaksiGajiService,
        private KaryawanRepository $karyawanRepository,
        private SlipGajiTemplateService $slipGajiTemplateService,
        private PlatformFeatureService $platformFeatureService,
    ) {
        $this->authorizeResource(TransaksiGaji::class, 'transaksi_gaji');
    }

    /**
     * Display a listing of the resource, clustered per karyawan.
     */
    public function index(Request $request)
    {
        $selectedKoperasiId = TenantContext::selectedKoperasiId($request);
        $search = $request->string('search')->trim()->value() ?: null;
        $perPage = PerPage::resolve($request);
        $karyawanList = $selectedKoperasiId
            ? $this->transaksiGajiService->karyawanList($search, $perPage, $selectedKoperasiId)
            : $this->transaksiGajiService->karyawanList($search, $perPage);
        $karyawanCetak = $this->karyawanRepository->withSalaryTransactionsOrderedList();
        $penandaTangan = $this->karyawanRepository->activeOrderedList();
        $paperLayoutDefault = $this->slipGajiTemplateService->publishedPaperLayout();

        return view('transaksi-gaji.index', [
            'karyawanList' => $karyawanList,
            'karyawanCetak' => $karyawanCetak,
            'penandaTangan' => $penandaTangan,
            'paperLayoutDefault' => $paperLayoutDefault,
            ...TenantContext::filterViewData($request, $selectedKoperasiId),
        ]);
    }

    /**
     * Display the full list of transaksi gaji for one specific karyawan.
     */
    public function karyawan(Request $request, Karyawan $karyawan)
    {
        $this->authorize('viewAny', TransaksiGaji::class);

        $transaksiGaji = $this->transaksiGajiService->listForKaryawan(
            $karyawan->id,
            $request->only(['bulan', 'tahun']),
            PerPage::resolve($request),
        );
        $penandaTangan = $this->karyawanRepository->activeOrderedList();
        $paperLayoutDefault = $this->slipGajiTemplateService->publishedPaperLayout();

        return view('transaksi-gaji.karyawan', compact(
            'karyawan',
            'transaksiGaji',
            'penandaTangan',
            'paperLayoutDefault',
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $transaksiGaji = new TransaksiGaji;
        $karyawans = $this->karyawanRepository->orderedList();
        [$barisMaster, $barisYatim] = $this->transaksiGajiService->formData(null);

        return view('transaksi-gaji.form', compact('transaksiGaji', 'karyawans', 'barisMaster', 'barisYatim'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransaksiGajiRequest $request)
    {
        $transaksiGaji = $this->transaksiGajiService->store($request->dataHeader(), $request->barisTerpilih());

        return redirect()->route('transaksi-gaji.show', $transaksiGaji)->with('success', 'Transaksi gaji berhasil disimpan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TransaksiGaji $transaksiGaji)
    {
        $transaksiGaji->load('koperasi:id,nama', 'karyawan.unitKerja', 'details');

        $totalTunjangan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Tunjangan');
        $totalPotongan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Potongan');
        $penandaTangan = $this->karyawanRepository->activeOrderedList();
        $paperLayoutDefault = $this->slipGajiTemplateService->publishedPaperLayout();
        $salarySlipPortalEnabled = $this->platformFeatureService->isEnabled('my_salary_slips');

        return view('transaksi-gaji.show', compact(
            'transaksiGaji',
            'totalTunjangan',
            'totalPotongan',
            'penandaTangan',
            'paperLayoutDefault',
            'salarySlipPortalEnabled',
        ));
    }

    public function publish(Request $request, TransaksiGaji $transaksiGaji)
    {
        abort_unless($this->platformFeatureService->isEnabled('my_salary_slips'), 404);

        $this->authorize('publish', $transaksiGaji);

        try {
            $this->transaksiGajiService->publish($transaksiGaji, $request->user()->id);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Slip gaji berhasil diterbitkan kepada karyawan.');
    }

    /**
     * Cetak slip gaji karyawan untuk transaksi ini.
     */
    public function cetak(CetakSlipGajiRequest $request, TransaksiGaji $transaksiGaji)
    {
        $this->authorize('cetak', $transaksiGaji);

        $transaksiGaji->load('karyawan.unitKerja', 'details');

        return $this->renderSlipGaji(collect([$transaksiGaji]), $request, route('transaksi-gaji.show', $transaksiGaji));
    }

    public function cetakMassal(CetakSlipGajiMassalRequest $request)
    {
        $this->authorize('cetak', TransaksiGaji::class);

        $transaksiGaji = $this->transaksiGajiService->slipsForBulkPrint($request->filter());
        $transaksiGaji->each(fn (TransaksiGaji $transaksi) => $this->authorize('cetak', $transaksi));

        return $this->renderSlipGaji($transaksiGaji, $request, route('transaksi-gaji.index'));
    }

    /**
     * @param  Collection<int, TransaksiGaji>  $transaksiGaji
     */
    private function renderSlipGaji(Collection $transaksiGaji, CetakSlipGajiRequest $request, string $backUrl)
    {
        [$dibuatOlehId, $mengetahuiId] = $request->penandaTanganIds();
        $penandaTangan = $this->karyawanRepository->activeByIds([$dibuatOlehId, $mengetahuiId]);

        abort_unless($penandaTangan->count() === 2, 422, 'Salah satu penanda tangan sudah tidak aktif.');
        $templateConfiguration = $this->slipGajiTemplateService->publishedConfiguration();
        $paperLayout = SlipGajiPaperLayout::normalize(
            $request->paperLayout() ?? $templateConfiguration['page']['paper_layout'],
        );

        return view('transaksi-gaji.cetak', [
            'slipPages' => $this->transaksiGajiService->slipPrintPages($transaksiGaji, $paperLayout),
            'dibuatOleh' => $penandaTangan->get($dibuatOlehId),
            'mengetahui' => $penandaTangan->get($mengetahuiId),
            'printedAt' => now(),
            'backUrl' => $backUrl,
            'isBulk' => $transaksiGaji->count() > 1,
            'templateConfiguration' => $templateConfiguration,
            'paperLayout' => $paperLayout,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TransaksiGaji $transaksiGaji)
    {
        $transaksiGaji->load('karyawan.unitKerja', 'details');

        $karyawans = $this->karyawanRepository->orderedList();
        [$barisMaster, $barisYatim] = $this->transaksiGajiService->formData($transaksiGaji);

        return view('transaksi-gaji.form', compact('transaksiGaji', 'karyawans', 'barisMaster', 'barisYatim'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransaksiGajiRequest $request, TransaksiGaji $transaksiGaji)
    {
        $this->transaksiGajiService->update($transaksiGaji, $request->dataHeader(), $request->barisTerpilih());

        return redirect()->route('transaksi-gaji.show', $transaksiGaji)->with('success', 'Transaksi gaji berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TransaksiGaji $transaksiGaji)
    {
        $karyawanId = $transaksiGaji->karyawan_id;

        try {
            $this->transaksiGajiService->destroy($transaksiGaji);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('transaksi-gaji.karyawan', ['karyawan' => $karyawanId])
            ->with('success', 'Transaksi gaji berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', TransaksiGaji::class);

        $transaksiGaji = TransaksiGaji::query()->whereKey($request->validated('ids'))->get();
        abort_unless($transaksiGaji->count() === count($request->validated('ids')), 422, 'Sebagian transaksi gaji sudah tidak tersedia.');

        $karyawanTerlibat = $transaksiGaji->pluck('karyawan_id')->unique();

        try {
            DB::transaction(fn () => $transaksiGaji->each(
                fn (TransaksiGaji $transaksi) => $this->transaksiGajiService->destroy($transaksi)
            ));
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $redirect = $karyawanTerlibat->count() === 1
            ? redirect()->route('transaksi-gaji.karyawan', ['karyawan' => $karyawanTerlibat->first()])
            : redirect()->route('transaksi-gaji.index');

        return $redirect->with('success', $transaksiGaji->count().' transaksi gaji berhasil dihapus.');
    }
}
