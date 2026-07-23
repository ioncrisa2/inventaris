<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\TransaksiGaji\StoreTransaksiGajiRequest;
use App\Http\Requests\TransaksiGaji\UpdateTransaksiGajiRequest;
use App\Models\TransaksiGaji;
use App\Repositories\KaryawanRepository;
use App\Services\TransaksiGajiService;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiGajiController extends Controller
{
    public function __construct(
        private TransaksiGajiService $transaksiGajiService,
        private KaryawanRepository $karyawanRepository,
    ) {
        $this->authorizeResource(TransaksiGaji::class, 'transaksi_gaji');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $transaksiGaji = $this->transaksiGajiService->list(
            $request->only(['search', 'bulan', 'tahun']),
            PerPage::resolve($request),
        );

        return view('transaksi-gaji.index', compact('transaksiGaji'));
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
        $transaksiGaji->load('karyawan.unitKerja', 'details');

        $totalTunjangan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Tunjangan');
        $totalPotongan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Potongan');

        return view('transaksi-gaji.show', compact('transaksiGaji', 'totalTunjangan', 'totalPotongan'));
    }

    /**
     * Cetak slip gaji karyawan untuk transaksi ini.
     */
    public function cetak(TransaksiGaji $transaksiGaji)
    {
        $this->authorize('view', $transaksiGaji);

        $transaksiGaji->load('karyawan.unitKerja', 'details');

        $totalTunjangan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Tunjangan');
        $totalPotongan = $this->transaksiGajiService->totalPerJenis($transaksiGaji, 'Potongan');

        return view('transaksi-gaji.cetak', compact('transaksiGaji', 'totalTunjangan', 'totalPotongan'));
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
        $this->transaksiGajiService->destroy($transaksiGaji);

        return redirect()->route('transaksi-gaji.index')->with('success', 'Transaksi gaji berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', TransaksiGaji::class);

        $transaksiGaji = TransaksiGaji::query()->whereKey($request->validated('ids'))->get();
        abort_unless($transaksiGaji->count() === count($request->validated('ids')), 422, 'Sebagian transaksi gaji sudah tidak tersedia.');

        DB::transaction(fn () => $transaksiGaji->each(
            fn (TransaksiGaji $transaksi) => $this->transaksiGajiService->destroy($transaksi)
        ));

        return redirect()->route('transaksi-gaji.index')
            ->with('success', $transaksiGaji->count().' transaksi gaji berhasil dihapus.');
    }
}
