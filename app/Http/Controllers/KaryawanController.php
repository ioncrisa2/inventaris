<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\Karyawan\StoreKaryawanRequest;
use App\Http\Requests\Karyawan\UpdateEmploymentStatusRequest;
use App\Http\Requests\Karyawan\UpdateKaryawanRequest;
use App\Models\Karyawan;
use App\Repositories\KaryawanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Services\KaryawanService;
use App\Support\PerPage;
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
        $karyawan = $this->karyawanService->list(
            $request->only(['search', 'unit_kerja_id', 'status_karyawan', 'kelengkapan']),
            PerPage::resolve($request),
        );
        $unitKerjas = $this->unitKerjaRepository->orderedList();

        return view('karyawan.index', compact('karyawan', 'unitKerjas'));
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
    public function show(Karyawan $karyawan)
    {
        $karyawan->load('unitKerja', 'dokumen', 'atasanLangsung');
        $usia = $karyawan->tanggal_lahir->age;
        $kategoriUsia = $this->karyawanService->kategoriUsia($karyawan);
        $masaKerja = $this->karyawanService->masaKerja($karyawan);

        return view('karyawan.show', compact('karyawan', 'usia', 'kategoriUsia', 'masaKerja'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Karyawan $karyawan)
    {
        $karyawan->loadCount('dokumen');
        $unitKerjas = $this->unitKerjaRepository->orderedList();
        $atasanOptions = $this->karyawanRepository->orderedList()->reject(fn ($k) => $k->id === $karyawan->id);

        return view('karyawan.form', compact('karyawan', 'unitKerjas', 'atasanOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKaryawanRequest $request, Karyawan $karyawan)
    {
        $this->karyawanService->update($karyawan, $request->validated());

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function updateEmploymentStatus(UpdateEmploymentStatusRequest $request, Karyawan $karyawan)
    {
        $tanggalKeluar = $request->validated('tanggal_mengundurkan_diri');
        $this->karyawanService->updateEmploymentStatus($karyawan, $tanggalKeluar);

        $message = $tanggalKeluar
            ? 'Karyawan berhasil dinonaktifkan.'
            : 'Karyawan berhasil diaktifkan kembali.';

        return redirect()->route('karyawan.show', $karyawan)->with('success', $message);
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
