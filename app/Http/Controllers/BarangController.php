<?php

namespace App\Http\Controllers;

use App\Http\Requests\Barang\CetakBarcodeMassalRequest;
use App\Http\Requests\Barang\StoreBarangRequest;
use App\Http\Requests\Barang\UpdateBarangRequest;
use App\Http\Requests\BulkDeleteRequest;
use App\Models\Barang;
use App\Repositories\RiwayatKondisiBarangRepository;
use App\Repositories\UnitKerjaRepository;
use App\Services\BarangService;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function __construct(
        private BarangService $barangService,
        private UnitKerjaRepository $unitKerjaRepository,
        private RiwayatKondisiBarangRepository $riwayatKondisiBarangRepository,
    ) {
        $this->authorizeResource(Barang::class, 'barang');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $barangs = $this->barangService->list($request->only(['search', 'unit_kerja_id', 'kategori', 'kondisi', 'kelengkapan']));
        $unitKerjas = $this->unitKerjaRepository->orderedList();

        return view('barang.index', compact('barangs', 'unitKerjas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $barang = new Barang;
        $unitKerjas = $this->unitKerjaRepository->orderedList();

        return view('barang.form', compact('barang', 'unitKerjas'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBarangRequest $request)
    {
        $this->barangService->store($request->validated());

        return redirect()->route('barang.index')
            ->with('success', 'Data barang berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Barang $barang)
    {
        $barang->load('unitKerja', 'kondisiTerakhir', 'fotoPendukung', 'dokumen');
        $riwayatKondisi = $this->riwayatKondisiBarangRepository->terbaruUntuk($barang);

        return view('barang.show', compact('barang', 'riwayatKondisi'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Barang $barang)
    {
        $barang->loadCount('dokumen');
        $unitKerjas = $this->unitKerjaRepository->orderedList();

        return view('barang.form', compact('barang', 'unitKerjas'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBarangRequest $request, Barang $barang)
    {
        $this->barangService->update($barang, $request->validated());

        return redirect()
            ->route('barang.index')
            ->with('success', 'Data barang berhasil diperbarui.');
    }

    /**
     * Tampilkan halaman cetak barcode Code128 untuk satu barang.
     */
    public function barcode(Barang $barang)
    {
        $this->authorize('view', $barang);
        $barang->load('unitKerja:id,nama_unit');

        $barcodeSvg = $this->barangService->barcodeCode128Svg($barang);

        return view('barang.barcode', compact('barang', 'barcodeSvg'));
    }

    /** Tampilkan halaman cetak QR Code yang membuka detail barang. */
    public function qrCode(Barang $barang)
    {
        $this->authorize('view', $barang);
        $barang->load('unitKerja:id,nama_unit');

        $qrSvg = $this->barangService->qrCodeSvg($barang);

        return view('barang.qr-code', compact('barang', 'qrSvg'));
    }

    /** Tampilkan lembar cetak barcode untuk barang yang dipilih dari tabel. */
    public function barcodeMassal(CetakBarcodeMassalRequest $request)
    {
        $ids = $request->validated('barang_ids');
        $barangs = Barang::query()
            ->with('unitKerja:id,nama_unit')
            ->whereIn('id', $ids)
            ->orderBy('kode_barang')
            ->get();

        $barcodeSvgs = $barangs->mapWithKeys(fn (Barang $barang) => [
            $barang->id => $this->barangService->barcodeCode128Svg($barang),
        ]);

        return view('barang.barcode-bulk', compact('barangs', 'barcodeSvgs'));
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', Barang::class);

        try {
            $jumlah = $this->barangService->destroyMany($request->validated('ids'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('barang.index')
            ->with('success', $jumlah.' barang berhasil dihapus.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Barang $barang)
    {
        try {
            $this->barangService->destroy($barang);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('barang.index')
            ->with('success', 'Data barang berhasil dihapus.');
    }
}
