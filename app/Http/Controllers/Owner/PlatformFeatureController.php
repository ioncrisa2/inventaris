<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\PlatformFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformFeatureController extends Controller
{
    public function __construct(private PlatformFeatureService $featureService) {}

    public function index(): View
    {
        $features = collect($this->featureService->catalog());

        return view('owner.features.index', [
            'featureGroups' => $features->groupBy('category'),
            'enabledCount' => $features->where('enabled', true)->count(),
            'disabledCount' => $features->where('enabled', false)->count(),
        ]);
    }

    public function update(Request $request, string $feature): RedirectResponse
    {
        abort_unless(array_key_exists($feature, $this->featureService->definitions()), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $this->featureService->setEnabled($feature, $enabled, $request->user());
        $label = $this->featureService->definitions()[$feature]['label'];

        return back()->with(
            'success',
            $enabled
                ? "Akses {$label} berhasil diaktifkan kembali."
                : "Akses {$label} berhasil dinonaktifkan secara global.",
        );
    }
}
