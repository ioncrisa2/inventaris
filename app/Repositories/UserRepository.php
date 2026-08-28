<?php

namespace App\Repositories;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository
{
    /**
     * @param  array{search?: ?string, role_id?: ?string, koperasi_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return CurrentTenant::scopeQuery(User::query())
            // Akun owner hanya diprovisikan melalui command server dan tidak
            // pernah menjadi bagian dari UI manajemen pengguna biasa.
            ->whereDoesntHave('roles', fn ($query) => $query
                ->where('roles.name', 'system_owner')
                ->whereNull('roles.koperasi_id'))
            ->with([
                'koperasi' => fn ($query) => $query
                    ->select('id', 'nama')
                    ->withCount('adminPrimerUsers'),
                'roles',
                'unitKerja:id,koperasi_id,nama_unit',
            ])
            ->when($filters['koperasi_id'] ?? null, function ($query, $koperasiId) {
                $query->where('koperasi_id', (int) $koperasiId);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role_id'] ?? null, function ($query, $roleId) {
                $query->whereHas('roles', function ($query) use ($roleId) {
                    $query->whereKey((int) $roleId);
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): User
    {
        $koperasiId = $data['koperasi_id'] ?? null;
        unset($data['koperasi_id']);

        $user = new User($data);
        $user->koperasi_id = $koperasiId;
        $user->save();

        return $user;
    }

    public function update(User $user, array $data): User
    {
        if (array_key_exists('koperasi_id', $data)) {
            $user->koperasi_id = $data['koperasi_id'];
            unset($data['koperasi_id']);
        }

        $user->update($data);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
