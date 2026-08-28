<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UpdateMaintenanceRequest;
use App\Services\PlatformMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private PlatformMaintenanceService $maintenanceService) {}

    public function edit(): View
    {
        return view('owner.maintenance', [
            'setting' => $this->maintenanceService->setting(),
        ]);
    }

    public function update(UpdateMaintenanceRequest $request): RedirectResponse
    {
        $this->maintenanceService->enable($request->user(), $request->validated());

        return back()->with('success', 'Soft maintenance berhasil diaktifkan atau dijadwalkan.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSystemOwner(), 403);
        $this->maintenanceService->disable($request->user());

        return back()->with('success', 'Soft maintenance berhasil dinonaktifkan.');
    }
}
