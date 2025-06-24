<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role; // Spatie'nin Role modelini import edin

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // BURADA ROL ATAMA KODU EKLENİYOR
        // 'user' rolünü veritabanında bul
        $userRole = Role::where('name', 'user')->first();

        // Eğer 'user' rolü mevcutsa, kullanıcıya ata
        if ($userRole) {
            $user->assignRole($userRole); // Spatie'nin assignRole metodunu kullan
        } else {
            // Eğer 'user' rolü bulunamazsa bir uyarı logu kaydet.
            // Bu durum genellikle rollerin veritabanında doğru şekilde seed edilmediği anlamına gelir.
            \Log::warning('Default "user" role not found when registering user: ' . $user->email);
            // İsterseniz burada bir hata fırlatabilir veya başka bir varsayılan rol atayabilirsiniz.
            // Örneğin: throw new \Exception('Default user role not configured.');
        }
        // ROL ATAMA KODU BİTİŞİ

        // TODO: refactor abilities of token
        $token = $user->createToken('api_auth_token')->plainTextToken;

        // Kullanıcı objesini döndürürken rollerini de yüklediğimizden emin olmalıyız
        // Frontend'in user.roles[0].name'i okuyabilmesi için bu önemli.
        return ResponseHelper::success('User registered successfully', [
            'user' => $user->load('roles'), // Rollere erişebilmesi için 'roles' ilişkisini yükle
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

        // Login yanıtında da kullanıcının rollerini yüklediğinizden emin olun
        return ResponseHelper::success('User logged in successfully', [
            'user' => $user->load('roles'), // Rollere erişebilmesi için 'roles' ilişkisini yükle
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ResponseHelper::success('User logged out successfully');
    }
}
