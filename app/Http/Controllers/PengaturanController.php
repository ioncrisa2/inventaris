<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pengaturan\UpdateHariOperasionalRequest;
use App\Http\Requests\Pengaturan\UpdateIdentitasAplikasiRequest;
use App\Http\Requests\Pengaturan\UpdatePengaturanRequest;
use App\Services\HariOperasionalService;
use App\Services\IdentitasAplikasiService;
use App\Services\KodeBarangGenerator;
use App\Services\SlipGajiTemplateService;

class PengaturanController extends Controller
{
    public function __construct(
        private KodeBarangGenerator $kodeBarangGenerator,
        private HariOperasionalService $hariOperasionalService,
        private IdentitasAplikasiService $identitasAplikasiService,
        private SlipGajiTemplateService $slipGajiTemplateService,
    ) {}

    public function edit()
    {
        $formatKodeBarang = null;
        $digitNomorUrut = null;
        $contohKodeBarang = null;
        $hariOperasional = null;
        $identitasNama = null;
        $identitasAlamat = null;
        $identitasLogoUrl = null;
        $slipGajiTemplateState = null;

        if (request()->user()->can('pengaturan.view')) {
            $formatKodeBarang = $this->kodeBarangGenerator->template();
            $digitNomorUrut = $this->kodeBarangGenerator->sequenceDigits();
            $contohKodeBarang = $this->kodeBarangGenerator->contoh($formatKodeBarang, $digitNomorUrut);
            $hariOperasional = $this->hariOperasionalService->hariOperasional();
            $identitasNama = $this->identitasAplikasiService->nama();
            $identitasAlamat = $this->identitasAplikasiService->alamat();
            $identitasLogoUrl = $this->identitasAplikasiService->logoUrl();
            $slipGajiTemplateState = $this->slipGajiTemplateService->editorState();
        }

        return view('pengaturan.edit', compact(
            'formatKodeBarang',
            'digitNomorUrut',
            'contohKodeBarang',
            'hariOperasional',
            'identitasNama',
            'identitasAlamat',
            'identitasLogoUrl',
            'slipGajiTemplateState',
        ));
    }

    public function update(UpdatePengaturanRequest $request)
    {
        try {
            $this->kodeBarangGenerator->simpanPengaturan(
                $request->validated('format_kode_barang'),
                (int) $request->validated('digit_nomor_urut'),
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pengaturan.edit')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function updateHariOperasional(UpdateHariOperasionalRequest $request)
    {
        try {
            $this->hariOperasionalService->simpan($request->validated('hari_operasional'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pengaturan.edit')->with('success', 'Hari operasional berhasil disimpan.');
    }

    public function updateIdentitas(UpdateIdentitasAplikasiRequest $request)
    {
        try {
            $this->identitasAplikasiService->simpan([
                'nama' => $request->validated('nama'),
                'alamat' => $request->validated('alamat'),
                'logo' => $request->file('logo'),
            ]);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pengaturan.edit')->with('success', 'Identitas koperasi berhasil disimpan.');
    }
}
