<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pengaturan\UpdateHariOperasionalRequest;
use App\Http\Requests\Pengaturan\UpdatePengaturanRequest;
use App\Services\HariOperasionalService;
use App\Services\KodeBarangGenerator;

class PengaturanController extends Controller
{
    public function __construct(
        private KodeBarangGenerator $kodeBarangGenerator,
        private HariOperasionalService $hariOperasionalService,
    ) {}

    public function edit()
    {
        $formatKodeBarang = null;
        $digitNomorUrut = null;
        $contohKodeBarang = null;
        $hariOperasional = null;

        if (request()->user()->can('pengaturan.view')) {
            $formatKodeBarang = $this->kodeBarangGenerator->template();
            $digitNomorUrut = $this->kodeBarangGenerator->sequenceDigits();
            $contohKodeBarang = $this->kodeBarangGenerator->contoh($formatKodeBarang, $digitNomorUrut);
            $hariOperasional = $this->hariOperasionalService->hariOperasional();
        }

        return view('pengaturan.edit', compact('formatKodeBarang', 'digitNomorUrut', 'contohKodeBarang', 'hariOperasional'));
    }

    public function update(UpdatePengaturanRequest $request)
    {
        $this->kodeBarangGenerator->simpanPengaturan(
            $request->validated('format_kode_barang'),
            (int) $request->validated('digit_nomor_urut'),
        );

        return redirect()->route('pengaturan.edit')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function updateHariOperasional(UpdateHariOperasionalRequest $request)
    {
        $this->hariOperasionalService->simpan($request->validated('hari_operasional'));

        return redirect()->route('pengaturan.edit')->with('success', 'Hari operasional berhasil disimpan.');
    }
}
