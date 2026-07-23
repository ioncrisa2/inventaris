<?php

namespace App\Http\Controllers;

use App\Http\Requests\RiwayatKondisiBarang\StoreRiwayatKondisiBarangRequest;
use App\Models\Barang;
use App\Services\RiwayatKondisiBarangService;

class RiwayatKondisiBarangController extends Controller
{
    public function __construct(private RiwayatKondisiBarangService $riwayatKondisiBarangService) {}

    public function store(StoreRiwayatKondisiBarangRequest $request, Barang $barang)
    {
        $validated = $request->validated();

        $this->riwayatKondisiBarangService->catat($barang, $validated);

        return redirect()
            ->route('barang.show', $barang)
            ->with('success', $this->riwayatKondisiBarangService->pesanUntuk($validated));
    }
}
