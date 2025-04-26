<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
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

        return ResponseHelper::success('User registered successfully', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ResponseHelper::error('Invalid credentials', 401);
        }

        $token = $user->createToken('api_auth_token')->plainTextToken;

        return ResponseHelper::success('User logged in successfully', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ResponseHelper::success('User logged out successfully');
    }
}
