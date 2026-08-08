<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OnboardingTourController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isTenantUser(), 403);

        if (is_null($user->onboarding_tour_finished_at)) {
            $user->onboarding_tour_finished_at = now();
            $user->save();
        }

        return response()->noContent();
    }
}
