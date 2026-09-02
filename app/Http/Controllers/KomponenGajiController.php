<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\KomponenGaji\KomponenGajiRequest;
use App\Models\KomponenGaji;
use App\Services\KomponenGajiService;
use App\Support\PerPage;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class KomponenGajiController extends Controller
{
    public function __construct(private KomponenGajiService $komponenGajiService)
    {
        $this->authorizeResource(KomponenGaji::class, 'komponen_gaji');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectedKoperasiId = TenantContext::selectedKoperasiId($request);
        $filters = $request->only(['search', 'jenis']);
        if ($selectedKoperasiId) {
            $filters['koperasi_id'] = $selectedKoperasiId;
        }
        $komponenGaji = $this->komponenGajiService->list(
            $filters,
            PerPage::resolve($request),
        );

        return view('komponen-gaji.index', [
            'komponenGaji' => $komponenGaji,
            ...TenantContext::filterViewData($request, $selectedKoperasiId),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('komponen-gaji.form', [
            'komponenGaji' => new KomponenGaji,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(KomponenGajiRequest $request)
    {
        $this->komponenGajiService->store($request->validated());

        return redirect()->route('komponen-gaji.index')->with('success', 'Komponen gaji berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(KomponenGaji $komponenGaji)
    {
        $komponenGaji->load([
            'rincian' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
        ]);

        return view('komponen-gaji.form', compact('komponenGaji'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(KomponenGajiRequest $request, KomponenGaji $komponenGaji)
    {
        $this->komponenGajiService->update($komponenGaji, $request->validated());

        return redirect()->route('komponen-gaji.index')->with('success', 'Komponen gaji berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KomponenGaji $komponenGaji)
    {
        try {
            $this->komponenGajiService->destroy($komponenGaji);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('komponen-gaji.index')->with('success', 'Komponen gaji berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', KomponenGaji::class);

        try {
            $jumlah = $this->komponenGajiService->destroyMany($request->validated('ids'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('komponen-gaji.index')
            ->with('success', $jumlah.' komponen gaji berhasil dihapus.');
    }
}
