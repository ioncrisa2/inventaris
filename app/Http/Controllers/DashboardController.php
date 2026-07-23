<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Handle the incoming request. Dashboard sendiri tidak digembok
     * permission (semua user yang login berhak masuk), tapi tiap
     * kartu/grafik/tabel di dalamnya punya permission "dashboard.*.view"
     * masing-masing, jadi datanya hanya dihitung & dikirim ke view kalau
     * user memang berhak melihat widget itu.
     */
    public function __invoke(Request $request)
    {
        return view('dashboard', $this->dashboardService->widgets(
            $request->user(),
            $request->string('periode')->toString(),
        ));
    }
}
