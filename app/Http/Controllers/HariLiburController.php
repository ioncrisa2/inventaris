<?php

namespace App\Http\Controllers;

use App\Exports\HariLiburTemplateExport;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\HariLibur\ImportHariLiburRequest;
use App\Http\Requests\HariLibur\StoreHariLiburRequest;
use App\Http\Requests\HariLibur\UpdateHariLiburRequest;
use App\Models\HariLibur;
use App\Models\Koperasi;
use App\Services\HariLiburService;
use App\Support\PerPage;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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
        return view('hari-libur.index', [
            'tahunList' => $this->hariLiburService->tahunList(),
        ]);
    }

    /**
     * Display the full list of hari libur for one specific tahun.
     */
    public function tahun(Request $request, int $tahun)
    {
        $this->authorize('viewAny', HariLibur::class);

        if ($request->user()->isSuperAdmin()) {
            $legacyKoperasiId = TenantContext::selectedKoperasiId($request);

            if ($legacyKoperasiId !== null) {
                return redirect()->route('hari-libur.koperasi', [
                    'tahun' => $tahun,
                    'koperasi' => $legacyKoperasiId,
                ]);
            }

            return view('hari-libur.tahun-koperasi', [
                'tahun' => $tahun,
                'koperasis' => $this->hariLiburService->koperasiListUntukTahun($tahun),
            ]);
        }

        $search = $request->string('search')->trim()->value() ?: null;
        $perPage = PerPage::resolve($request);

        return view('hari-libur.tahun', [
            'hariLibur' => $this->hariLiburService->listForTahun($tahun, $search, $perPage),
            'tahun' => $tahun,
            'koperasi' => $request->user()->koperasi,
        ]);
    }

    public function koperasi(Request $request, int $tahun, Koperasi $koperasi)
    {
        $this->authorize('viewAny', HariLibur::class);
        abort_unless($request->user()->isSuperAdmin(), 403);

        $search = $request->string('search')->trim()->value() ?: null;
        $perPage = PerPage::resolve($request);

        return view('hari-libur.tahun', [
            'hariLibur' => $this->hariLiburService->listForTahun(
                $tahun,
                $search,
                $perPage,
                $koperasi->id,
            ),
            'tahun' => $tahun,
            'koperasi' => $koperasi,
        ]);
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

    /**
     * Unduh template Excel kosong (kolom Tanggal & Keterangan) untuk diisi
     * lalu diunggah lewat import().
     */
    public function template()
    {
        $this->authorize('create', HariLibur::class);

        return Excel::download(new HariLiburTemplateExport, 'template-hari-libur.xlsx');
    }

    /**
     * Import hari libur secara massal dari file Excel/CSV. Tanggal yang
     * sudah ada di database tidak pernah diubah — cuma menambah yang
     * benar-benar belum ada, sama seperti input manual satu per satu.
     */
    public function import(ImportHariLiburRequest $request)
    {
        $hasil = $this->hariLiburService->import($request->file('file'));

        $pesan = $hasil['ditambahkan'] > 0
            ? "{$hasil['ditambahkan']} hari libur berhasil ditambahkan dari file."
            : 'Tidak ada hari libur baru yang ditambahkan.';

        if ($hasil['sudah_ada'] > 0) {
            $pesan .= " {$hasil['sudah_ada']} baris dilewati karena tanggalnya sudah ada.";
        }

        if ($hasil['gagal'] > 0) {
            $pesan .= " {$hasil['gagal']} baris dilewati karena tidak valid.";
        }

        return redirect()->route('hari-libur.index')->with('success', $pesan);
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
