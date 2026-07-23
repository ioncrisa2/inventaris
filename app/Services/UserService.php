<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private UserRepository $userRepository) {}

    /**
     * @param  array{search?: ?string, role?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters, $perPage);
    }

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
            ]);

            $user->syncRoles([$data['role']]);

            return $user;
        }, 3);
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $this->userRepository->update($user, [
                'name' => $data['name'],
                'email' => $data['email'],
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
                ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
            ]);

            $user->syncRoles([$data['role']]);

            return $user;
        }, 3);
    }

    /**
     * @throws \DomainException Jika actor mencoba menghapus akunnya sendiri.
     */
    public function destroy(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new \DomainException('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $this->userRepository->delete($target);
    }
}
