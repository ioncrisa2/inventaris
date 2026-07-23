<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardBannerController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $user = $request->user();

        if (is_null($user->dashboard_banner_dismissed_at)) {
            $user->dashboard_banner_dismissed_at = now();
            $user->save();
        }

        return redirect()->route('dashboard', array_filter($validated));
    }
}
