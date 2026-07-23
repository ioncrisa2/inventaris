<?php

namespace App\Http\Controllers;

use App\Http\Requests\FotoBarang\StoreFotoBarangRequest;
use App\Models\Barang;
use App\Models\FotoBarang;
use App\Services\FotoBarangService;

class FotoBarangController extends Controller
{
    public function __construct(private FotoBarangService $fotoBarangService) {}

    public function store(StoreFotoBarangRequest $request, Barang $barang)
    {
        $this->fotoBarangService->store($barang, $request->validated());

        return redirect()->route('barang.show', $barang)->with('success', 'Foto pendukung berhasil ditambahkan.');
    }

    public function destroy(Barang $barang, FotoBarang $fotoBarang)
    {
        $this->authorize('update', $barang);
        abort_unless($fotoBarang->barang_id === $barang->id, 404);

        $this->fotoBarangService->destroy($barang, $fotoBarang);

        return redirect()->route('barang.show', $barang)->with('success', 'Foto pendukung berhasil dihapus.');
    }
}
