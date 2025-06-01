<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorRestrictedTimeFrame;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class QuickTestSeeder extends Seeder
{
    /**
     * Run the database seeds for quick testing.
     * This seeder creates a minimal but complete dataset.
     */
    public function run(): void
    {
        $this->command->info('Creating roles...');
        $this->createRoles();

        $this->command->info('Creating users...');
        // Create admin
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
        $admin->assignRole('admin');

        // Create 3 regular users
        $users = User::factory(3)->create();
        $users->each(fn($user) => $user->assignRole('user'));

        $this->command->info('Creating doctors...');
        // Create 3 doctors
        $doctors = collect();
        for ($i = 1; $i <= 3; $i++) {
            $doctorUser = User::factory()->create([
                'name' => "Dr. Test Doctor {$i}",
                'email' => "doctor{$i}@test.com",
            ]);
            $doctorUser->assignRole('doctor');
            
            $doctor = Doctor::factory()->create([
                'user_id' => $doctorUser->id,
            ]);
            $doctors->push($doctor);
        }

        $this->command->info('Creating pets...');
        // Create 2 pets for each user
        $pets = collect();
        $users->each(function ($user) use (&$pets) {
            $userPets = Pet::factory(2)->create([
                'owner_id' => $user->id,
            ]);
            $pets = $pets->merge($userPets);
        });

        $this->command->info('Creating appointments...');
        // Create 10 appointments
        for ($i = 0; $i < 10; $i++) {
            $user = $users->random();
            $userPets = $pets->where('owner_id', $user->id);
            $pet = $userPets->random();
            
            Appointment::factory()->create([
                'doctor_id' => $doctors->random()->id,
                'user_id' => $user->id,
                'pet_id' => $pet->id,
            ]);
        }

        $this->command->info('Creating doctor restrictions...');
        // Create some restricted time frames
        $doctors->each(function ($doctor) {
            if (rand(0, 1)) { // 50% chance
                DoctorRestrictedTimeFrame::factory()->create([
                    'doctor_id' => $doctor->id,
                ]);
            }
        });

        $this->command->info('Quick test data seeding completed!');
        $this->command->info("Created:");
        $this->command->info("- 1 Admin user (admin@test.com)");
        $this->command->info("- 3 Regular users");
        $this->command->info("- 3 Doctors (doctor1@test.com, doctor2@test.com, doctor3@test.com)");
        $this->command->info("- 6 Pets");
        $this->command->info("- 10 Appointments");
        $this->command->info("- Some doctor restrictions");
        $this->command->info("Default password for all users: 'password'");
    }

    private function createRoles(): void
    {
        $roles = ['admin', 'doctor', 'user'];
        
        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'sanctum'
            ]);
        }
    }
} 