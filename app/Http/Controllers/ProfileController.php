<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Repositories\UnitKerjaRepository;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
        private UnitKerjaRepository $unitKerjaRepository,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $unitKerjas = $this->unitKerjaRepository->orderedList();

        return view('profile.show', compact('user', 'unitKerjas'));
    }

    public function update(UpdateProfileRequest $request)
    {
        $this->profileService->updateInfo($request->user(), $request->validated());

        return redirect()
            ->route('profile.show')
            ->with('profile_success', 'Informasi akun berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'));

        return redirect()
            ->to(route('profile.show').'#keamanan')
            ->with('password_success', 'Password berhasil diperbarui.');
    }
}
