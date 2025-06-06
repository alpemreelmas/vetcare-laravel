<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = Doctor::all();
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'user');
        })->get();
        $pets = Pet::all();
        
        // If no users with 'user' role exist, get all users except doctors
        if ($users->isEmpty()) {
            $users = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'doctor');
            })->get();
        }
        
        // Create appointments only if we have the necessary data
        if ($doctors->isNotEmpty() && $users->isNotEmpty() && $pets->isNotEmpty()) {
            // Create 30 appointments with existing relationships
            for ($i = 0; $i < 30; $i++) {
                $user = $users->random();
                $userPets = $pets->where('owner_id', $user->id);
                
                // If user has pets, use one of them, otherwise use any pet
                $pet = $userPets->isNotEmpty() ? $userPets->random() : $pets->random();
                
                Appointment::factory()->create([
                    'doctor_id' => $doctors->random()->id,
                    'user_id' => $user->id,
                    'pet_id' => $pet->id,
                ]);
            }
        } else {
            // Fallback: create appointments with factory relationships
            Appointment::factory()->count(20)->create();
        }
    }
}
