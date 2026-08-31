<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
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
    private const ROUTE_PREFIX = 'owner.roles';

    public function __construct(private RoleService $roleService) {}

    public function index(Request $request)
    {
        $roles = $this->roleService->list($request->user(), PerPage::resolve($request));

        return view('role.index', [
            'roles' => $roles,
            'routePrefix' => self::ROUTE_PREFIX,
            'isOwnerRoleManager' => true,
        ]);
    }

    public function create()
    {
        return view('role.form', [
            'role' => new Role,
            'permissionGroups' => PermissionCatalog::groups(),
            'selectedPermissions' => [],
            'koperasis' => Koperasi::query()->orderBy('nama')->get(),
            'routePrefix' => self::ROUTE_PREFIX,
            'isOwnerRoleManager' => true,
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            $this->roleService->store($request->user(), $request->validated());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()->route(self::ROUTE_PREFIX.'.index')
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function edit(Role $role)
    {
        abort_if($role->name === 'system_owner', 404);

        return view('role.form', [
            'role' => $role,
            'permissionGroups' => PermissionCatalog::groups(),
            'selectedPermissions' => $role->permissions->pluck('name')->all(),
            'routePrefix' => self::ROUTE_PREFIX,
            'isOwnerRoleManager' => true,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        abort_if($role->name === 'system_owner', 404);

        try {
            $this->roleService->update($request->user(), $role, $request->validated());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()->route(self::ROUTE_PREFIX.'.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role)
    {
        try {
            $this->roleService->destroy($request->user(), $role);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route(self::ROUTE_PREFIX.'.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        try {
            $count = $this->roleService->destroyMany($request->user(), $request->validated('ids'));
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route(self::ROUTE_PREFIX.'.index')
            ->with('success', $count.' role berhasil dihapus.');
    }
}
