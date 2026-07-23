<?php

namespace App\Http\Requests\Role;

class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('role.update');
    }

    protected function roleId(): ?int
    {
        return $this->route('role')?->id;
    }
}
