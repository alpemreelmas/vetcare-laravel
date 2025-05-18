<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Role::create([
            'name' => 'admin',
            'guard_name' => 'sanctum'
        ]);
        Role::create([
            'name' => 'user',
            'guard_name' => 'sanctum'
        ]);

        $adminUser->assignRole('admin');

        $users = User::factory(4)->create();
        $users->each(function ($user) {
            $user->assignRole('user');
        });
    }
}
