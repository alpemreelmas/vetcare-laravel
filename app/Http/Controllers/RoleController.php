<?php

namespace App\Http\Controllers;

use App\Core\BaseApiController;
use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends BaseApiController
{
    public function index()
    {
        $roles = Role::all();
        return self::success(data: $roles);
    }

    public function store(StoreRequest $request)
    {
        $role = Role::create(['name' => $request->name]);

        return self::success(data: [
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ]);
    }

    public function show(Role $role)
    {
        return self::success(data: [
            'role' => $role->load('permissions')
        ]);
    }

    public function update(UpdateRequest $request, Role $role)
    {
        $role->update(['name' => $request->name]);
        return self::success(data: [
            'message' => 'Role updated successfully',
            'role' => $role->load("permissions")
        ]);
    }

    public function destroy(Role $role)
    {
        $users = User::whereRelation('roles', 'role_id', $role->id)->get();

        if ($users->count() > 0) {
            return self::error(
                message: 'Role cannot be deleted because it is assigned to users',
                status: 400
            );
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
