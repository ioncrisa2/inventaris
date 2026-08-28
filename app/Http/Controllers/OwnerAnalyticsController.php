<?php

namespace App\Http\Controllers;

use App\Http\Requests\Owner\OwnerAnalyticsRequest;
use App\Models\Koperasi;
use App\Services\OwnerAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OwnerAnalyticsController extends Controller
{
    public function __construct(private OwnerAnalyticsService $analyticsService) {}

    public function index(OwnerAnalyticsRequest $request): View|RedirectResponse
    {
        $filters = $request->filters();

        if ($filters['koperasi_id'] !== null) {
            return redirect()->route('owner.analytics.koperasi', [
                'koperasi' => $filters['koperasi_id'],
                'tanggal_awal' => $filters['tanggal_awal'],
                'tanggal_akhir' => $filters['tanggal_akhir'],
                'modul' => $filters['modul'],
            ]);
        }

        $analytics = $this->analyticsService->analytics($filters);

        return view('owner.analytics.index', [
            'analytics' => $analytics,
            'filters' => $filters,
        ]);
    }

    public function koperasi(OwnerAnalyticsRequest $request, Koperasi $koperasi): View
    {
        return $this->koperasiResponse($request, $koperasi);
    }

    /** Alias konvensional bila route resource memilih nama metode `show`. */
    public function show(OwnerAnalyticsRequest $request, Koperasi $koperasi): View
    {
        return $this->koperasiResponse($request, $koperasi);
    }

    private function koperasiResponse(OwnerAnalyticsRequest $request, Koperasi $koperasi): View
    {
        $filters = $request->filters();
        $analytics = $this->analyticsService->koperasi((int) $koperasi->getKey(), $filters);

        return view('owner.analytics.koperasi', [
            'analytics' => $analytics,
            'filters' => $filters,
            // Array metadata control-plane, bukan model Eloquent.
            'koperasi' => $analytics['koperasi'],
        ]);
    }
}
