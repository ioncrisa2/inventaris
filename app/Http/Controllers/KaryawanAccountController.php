<?php

namespace App\Http\Controllers;

use App\Http\Requests\Karyawan\LinkUserRequest;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\KaryawanAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KaryawanAccountController extends Controller
{
    public function __construct(private KaryawanAccountService $accountService) {}

    public function update(LinkUserRequest $request, Karyawan $karyawan): RedirectResponse
    {
        try {
            $target = User::query()->findOrFail((int) $request->validated('user_id'));
            $this->accountService->link($request->user(), $karyawan, $target);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Akun login berhasil dihubungkan dengan karyawan.');
    }

    public function destroy(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $this->authorize('manageAccount', $karyawan);

        try {
            $this->accountService->unlink($request->user(), $karyawan);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Hubungan akun login berhasil dilepas.');
    }
}
