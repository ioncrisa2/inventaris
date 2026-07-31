<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Koperasi;
use App\Models\Role;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use App\Support\PerPage;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('role.view');

        $roles = $this->roleService->list(PerPage::resolve($request));

        return view('role.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('role.create');
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $role = new Role;
        $permissionGroups = PermissionCatalog::groups();
        $selectedPermissions = [];
        $koperasis = Koperasi::orderBy('nama')->get();

        return view('role.form', compact('role', 'permissionGroups', 'selectedPermissions', 'koperasis'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $this->roleService->store($request->user(), $request->validated());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('role.index')->with('success', 'Role berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $this->authorize('role.update');
        $this->abortIfOtherTenant($role);

        $permissionGroups = PermissionCatalog::groups();
        $selectedPermissions = $role->permissions->pluck('name')->all();

        return view('role.form', compact('role', 'permissionGroups', 'selectedPermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->abortIfOtherTenant($role);

        $this->roleService->update($role, $request->validated());

        return redirect()->route('role.index')->with('success', 'Role berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Role $role)
    {
        $this->authorize('role.delete');

        try {
            $this->roleService->destroy($request->user(), $role);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('role.index')->with('success', 'Role berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('role.delete');

        try {
            $jumlah = $this->roleService->destroyMany($request->user(), $request->validated('ids'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('role.index')
            ->with('success', $jumlah.' role berhasil dihapus.');
    }

    /**
     * Role TIDAK pakai global scope tenant (lihat catatan di App\Models\Role),
     * jadi route model binding {role} tidak otomatis 404 untuk role milik
     * koperasi lain — dicek manual di sini untuk aksi yang melihat/mengubah
     * satu role spesifik.
     */
    private function abortIfOtherTenant(Role $role): void
    {
        $user = auth()->user();

        abort_if(! $user->isSuperAdmin() && $role->koperasi_id !== $user->koperasi_id, 404);
    }
}
