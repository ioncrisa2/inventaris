<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\Karyawan\StoreKaryawanRequest;
use App\Models\Karyawan;
use App\Repositories\KaryawanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Services\KaryawanService;
use App\Support\KaryawanPerubahanSchema;
use App\Support\PerPage;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class KaryawanController extends Controller
{
    public function __construct(
        private KaryawanService $karyawanService,
        private UnitKerjaRepository $unitKerjaRepository,
        private KaryawanRepository $karyawanRepository,
    ) {
        $this->authorizeResource(Karyawan::class, 'karyawan');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectedKoperasiId = TenantContext::selectedKoperasiId($request);
        $filters = $request->only(['search', 'unit_kerja_id', 'status_karyawan', 'kelengkapan']);
        if ($selectedKoperasiId) {
            $filters['koperasi_id'] = $selectedKoperasiId;
        }
        $karyawan = $this->karyawanService->list(
            $filters,
            PerPage::resolve($request),
        );
        $unitKerjas = $selectedKoperasiId
            ? $this->unitKerjaRepository->orderedList($selectedKoperasiId)
            : $this->unitKerjaRepository->orderedList();

        return view('karyawan.index', [
            'karyawan' => $karyawan,
            'unitKerjas' => $unitKerjas,
            ...TenantContext::filterViewData($request, $selectedKoperasiId),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $karyawan = new Karyawan;
        $unitKerjas = $this->unitKerjaRepository->orderedList();
        $atasanOptions = $this->karyawanRepository->orderedList();

        return view('karyawan.form', compact('karyawan', 'unitKerjas', 'atasanOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKaryawanRequest $request)
    {
        $this->karyawanService->store($request->validated());

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Karyawan $karyawan)
    {
        $karyawan->load('koperasi:id,nama', 'unitKerja', 'dokumen', 'atasanLangsung');
        if ($request->user()->can('karyawan.riwayat.view')) {
            $karyawan->load([
                'riwayatPerubahan' => fn ($query) => $query
                    ->with(['pelaku:id,name', 'perubahan', 'dokumen'])
                    ->orderByDesc('tanggal_berlaku')
                    ->orderByDesc('created_at'),
            ]);
        }

        $usia = $karyawan->tanggal_lahir->age;
        $kategoriUsia = $this->karyawanService->kategoriUsia($karyawan);
        $masaKerja = $this->karyawanService->masaKerja($karyawan);
        $jenisPerubahanTersedia = KaryawanPerubahanSchema::allowedTypesFor($request->user());
        $unitKerjas = $jenisPerubahanTersedia === []
            ? collect()
            : $this->unitKerjaRepository->orderedList();
        $atasanOptions = $jenisPerubahanTersedia === []
            ? collect()
            : $this->karyawanRepository->orderedList()->reject(fn ($item) => $item->id === $karyawan->id);

        return view('karyawan.show', compact(
            'karyawan',
            'usia',
            'kategoriUsia',
            'masaKerja',
            'jenisPerubahanTersedia',
            'unitKerjas',
            'atasanOptions',
        ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Karyawan $karyawan)
    {
        try {
            $this->karyawanService->destroy($karyawan);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', Karyawan::class);

        try {
            $jumlah = $this->karyawanService->destroyMany($request->validated('ids'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('karyawan.index')
            ->with('success', $jumlah.' karyawan berhasil dihapus.');
    }
}
