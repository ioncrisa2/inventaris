<?php

namespace App\Http\Controllers;

use App\Http\Requests\DokumenBarang\StoreDokumenBarangRequest;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Services\DokumenBarangService;

class DokumenBarangController extends Controller
{
    public function __construct(private DokumenBarangService $dokumenBarangService) {}

    public function store(StoreDokumenBarangRequest $request, Barang $barang)
    {
        $this->dokumenBarangService->store($barang, $request->validated());

        return redirect()->route('barang.show', $barang)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function download(Barang $barang, DokumenBarang $dokumenBarang)
    {
        $this->authorize('view', $barang);
        abort_unless($dokumenBarang->barang_id === $barang->id, 404);

        return $this->dokumenBarangService->streamedDownload($dokumenBarang);
    }

    public function destroy(Barang $barang, DokumenBarang $dokumenBarang)
    {
        $this->authorize('update', $barang);
        abort_unless($dokumenBarang->barang_id === $barang->id, 404);

        $this->dokumenBarangService->destroy($barang, $dokumenBarang);

        return redirect()->route('barang.show', $barang)->with('success', 'Dokumen berhasil dihapus.');
    }
}
