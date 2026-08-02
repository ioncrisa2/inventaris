<?php

namespace App\Services;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function updateInfo(User $user, array $data): User
    {
        $unitKerjaId = $data['unit_kerja_id'] ?? null;

        if ($unitKerjaId !== null) {
            $valid = $user->koperasi_id !== null
                && UnitKerja::withoutGlobalScopes()
                    ->whereKey((int) $unitKerjaId)
                    ->where('koperasi_id', $user->koperasi_id)
                    ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'unit_kerja_id' => 'Unit kerja tidak berada dalam koperasi akun Anda.',
                ])->errorBag('updateProfile');
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->unit_kerja_id = $data['unit_kerja_id'] ?? null;
            $user->save();

            return $user;
        }, 3);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        DB::transaction(fn () => $user->update(['password' => Hash::make($newPassword)]), 3);
    }
}
