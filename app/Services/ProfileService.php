<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function updateInfo(User $user, array $data): User
    {
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
