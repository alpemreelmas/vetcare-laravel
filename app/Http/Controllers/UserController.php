<?php

namespace App\Http\Controllers;

use App\Core\BaseApiController;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends BaseApiController
{
    public function index()
    {
        return User::with('roles', 'permissions')->get();
    }

    public function show($userId)
    {
        $user = User::with(['roles', 'permissions'])->findOrFail($userId);
        $roles = Role::all();

        return self::success(data:[
            'user' => $user,
            'roles' => $roles
        ]);
    }
    // TODO: add user store method for privilege users (admin)
    public function update(UpdateRequest $request, $userId)
    {
        $user = User::findOrFail($userId);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        if($request->has("password")) {
            $user->update([
                'password' => Hash::make($request->password)
            ]);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return self::success(data: [
            'user' => $user->load('roles', 'permissions'),
            'message' => 'User roles updated successfully'
        ]);
    }
}
