<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserListController extends Controller
{
    /**
     * Daftar baca-saja akun tenant untuk control plane System Owner.
     * CRUD pengguna tetap berada di area operasional per tenant.
     */
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        $users = User::query()
            ->whereNotNull('koperasi_id')
            ->whereDoesntHave('roles', fn ($query) => $query
                ->where('roles.name', 'system_owner')
                ->whereNull('roles.koperasi_id'))
            ->with([
                'koperasi:id,nama',
                'roles:id,name,koperasi_id',
                // UnitKerja menggunakan scope tenant. Owner tidak memiliki
                // tenant aktif, sehingga relasi ini harus dibaca eksplisit.
                'unitKerja' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->select('id', 'koperasi_id', 'nama_unit'),
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query
                ->where('koperasi_id', $koperasiId))
            ->when($filters['role_id'] ?? null, fn ($query, $roleId) => $query
                ->whereHas('roles', fn ($query) => $query->whereKey($roleId)))
            ->orderBy('name')
            ->paginate(PerPage::resolve($request, 25))
            ->withQueryString();

        return view('owner.userlist.index', [
            'users' => $users,
            'koperasis' => Koperasi::query()->orderBy('nama')->get(['id', 'nama']),
            'roles' => Role::query()
                ->whereNotNull('koperasi_id')
                ->with('koperasi:id,nama')
                ->orderBy('name')
                ->get(['id', 'name', 'koperasi_id']),
        ]);
    }
}
