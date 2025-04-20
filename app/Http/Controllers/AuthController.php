<?php

namespace App\Http\Controllers;

use App\Core\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // TODO: refactor abilities of token
        $token = $user->createToken('api_auth_token')->plainTextToken;

        return self::success('User registered successfully', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return self::error('Invalid credentials', 401);
        }

        $token = $user->createToken('api_auth_token')->plainTextToken;

        return self::success('User logged in successfully', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return self::success('User logged out successfully');
    }
}
