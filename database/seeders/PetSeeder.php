<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users with 'user' role (not doctors or admins)
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'user');
        })->get();
        
        // If no users with 'user' role exist, get all users
        if ($users->isEmpty()) {
            $users = User::all();
        }
        
        // Create 1-3 pets for each user
        $users->each(function ($user) {
            $petCount = rand(1, 3);
            Pet::factory()->count($petCount)->create([
                'owner_id' => $user->id,
            ]);
        });
        
        // Create some additional pets with new users
        Pet::factory()->count(10)->create();
    }
}
