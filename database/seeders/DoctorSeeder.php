<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorRestrictedTimeFrame;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 8 doctors with their associated users
        $doctors = collect();
        
        for ($i = 0; $i < 8; $i++) {
            // Create a user for the doctor
            $user = User::factory()->create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
            ]);
            
            // Assign doctor role if it exists
            $doctorRole = Role::where('name', 'doctor')->first();
            if ($doctorRole) {
                $user->assignRole('doctor');
            }
            
            // Create the doctor
            $doctor = Doctor::factory()->create([
                'user_id' => $user->id,
            ]);
            
            $doctors->push($doctor);
        }
        
        // Create some restricted time frames for random doctors
        $doctors->random(4)->each(function ($doctor) {
            DoctorRestrictedTimeFrame::factory()->count(rand(1, 3))->create([
                'doctor_id' => $doctor->id,
            ]);
        });
    }
}
