<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\HariLibur\StoreHariLiburRequest;
use App\Http\Requests\HariLibur\UpdateHariLiburRequest;
use App\Models\HariLibur;
use App\Services\HariLiburService;
use App\Support\PerPage;
use Illuminate\Http\Request;

class HariLiburController extends Controller
{
    public function __construct(private HariLiburService $hariLiburService)
    {
        $this->authorizeResource(HariLibur::class, 'hari_libur');
    }

    /**
     * Display a listing of the resource, clustered per tahun.
     */
    public function index()
    {
        $tahunList = $this->hariLiburService->tahunList();

        return view('hari-libur.index', compact('tahunList'));
    }

    /**
     * Display the full list of hari libur for one specific tahun.
     */
    public function tahun(Request $request, int $tahun)
    {
        $this->authorize('viewAny', HariLibur::class);

        $hariLibur = $this->hariLiburService->listForTahun(
            $tahun,
            $request->string('search')->trim()->value() ?: null,
            PerPage::resolve($request),
        );

        return view('hari-libur.tahun', compact('hariLibur', 'tahun'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHariLiburRequest $request)
    {
        $hariLibur = $this->hariLiburService->store($request->validated());

        return redirect()->route('hari-libur.tahun', ['tahun' => $hariLibur->tanggal->year])
            ->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHariLiburRequest $request, HariLibur $hariLibur)
    {
        $hariLibur = $this->hariLiburService->update($hariLibur, $request->validated());

        return redirect()->route('hari-libur.tahun', ['tahun' => $hariLibur->tanggal->year])
            ->with('success', 'Hari libur berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HariLibur $hariLibur)
    {
        $tahun = $hariLibur->tanggal->year;

        try {
            $this->hariLiburService->destroy($hariLibur);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hari-libur.tahun', ['tahun' => $tahun])
            ->with('success', 'Hari libur berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', HariLibur::class);

        $ids = $request->validated('ids');
        $tahunTerlibat = HariLibur::query()->whereKey($ids)->get()
            ->map(fn (HariLibur $hariLibur) => $hariLibur->tanggal->year)
            ->unique();

        try {
            $jumlah = $this->hariLiburService->destroyMany($ids);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $redirect = $tahunTerlibat->count() === 1
            ? redirect()->route('hari-libur.tahun', ['tahun' => $tahunTerlibat->first()])
            : redirect()->route('hari-libur.index');

        return $redirect->with('success', $jumlah.' hari libur berhasil dihapus.');
    }
}
