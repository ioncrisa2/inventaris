<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\UnitKerja\StoreUnitKerjaRequest;
use App\Http\Requests\UnitKerja\UpdateUnitKerjaRequest;
use App\Models\UnitKerja;
use App\Services\UnitKerjaService;
use App\Support\PerPage;
use Illuminate\Http\Request;

class UnitKerjaController extends Controller
{
    public function __construct(private UnitKerjaService $unitKerjaService)
    {
        $this->authorizeResource(UnitKerja::class, 'unit_kerja');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $unitKerja = $this->unitKerjaService->list(
            $request->string('search')->trim()->value() ?: null,
            PerPage::resolve($request),
        );

        return view('unit-kerja.index', compact('unitKerja'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUnitKerjaRequest $request)
    {
        $this->unitKerjaService->store($request->validated());

        return redirect()->route('unit-kerja.index')->with('success', 'Unit Kerja berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUnitKerjaRequest $request, UnitKerja $unitKerja)
    {
        $this->unitKerjaService->update($unitKerja, $request->validated());

        return redirect()->route('unit-kerja.index')->with('success', 'Unit Kerja berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UnitKerja $unitKerja)
    {
        try {
            $this->unitKerjaService->destroy($unitKerja);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('unit-kerja.index')->with('success', 'Unit Kerja berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', UnitKerja::class);

        try {
            $jumlah = $this->unitKerjaService->destroyMany($request->validated('ids'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('unit-kerja.index')
            ->with('success', $jumlah.' unit kerja berhasil dihapus.');
    }
}
