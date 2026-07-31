<?php

namespace App\Http\Controllers;

use App\Http\Requests\Koperasi\StoreKoperasiRequest;
use App\Http\Requests\Koperasi\UpdateKoperasiRequest;
use App\Models\Koperasi;
use App\Services\KoperasiService;
use App\Support\PerPage;
use Illuminate\Http\Request;

class KoperasiController extends Controller
{
    public function __construct(private KoperasiService $koperasiService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $koperasis = $this->koperasiService->list(
            $request->string('search')->trim()->value() ?: null,
            PerPage::resolve($request),
        );

        return view('koperasi.index', compact('koperasis'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $koperasi = new Koperasi;

        return view('koperasi.form', compact('koperasi'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKoperasiRequest $request)
    {
        $this->koperasiService->store($request->validated());

        return redirect()->route('koperasi.index')->with('success', 'Koperasi berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Koperasi $koperasi)
    {
        return view('koperasi.form', compact('koperasi'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKoperasiRequest $request, Koperasi $koperasi)
    {
        $this->koperasiService->update($koperasi, $request->validated());

        return redirect()->route('koperasi.index')->with('success', 'Koperasi berhasil diperbarui.');
    }
}
