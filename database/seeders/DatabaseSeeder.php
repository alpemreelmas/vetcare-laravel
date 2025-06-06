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
        $this->command->info('🌱 Starting VetCare database seeding...');

        // Create roles first
        $this->createRoles();
        
        // Create admin user
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@vetcare.com',
        ]);
        $adminUser->assignRole('admin');
        $this->command->info('✅ Admin user created: admin@vetcare.com');

        // Create some regular users
        $users = User::factory(15)->create();
        $users->each(function ($user) {
            $user->assignRole('user');
        });
        $this->command->info('✅ Created 15 regular users');

        // Run other seeders in order
        $this->call([
            DoctorSeeder::class,
            PetSeeder::class,
            ServiceSeeder::class,
            AppointmentSeeder::class,
            DoctorRestrictedTimeFrameSeeder::class,
            MedicalDataSeeder::class,
        ]);

        $this->command->info('🎉 VetCare database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   • Users: ' . User::count());
        $this->command->info('   • Doctors: ' . \App\Models\Doctor::count());
        $this->command->info('   • Pets: ' . \App\Models\Pet::count());
        $this->command->info('   • Services: ' . \App\Models\Service::count());
        $this->command->info('   • Appointments: ' . \App\Models\Appointment::count());
        $this->command->info('   • Medical Records: ' . \App\Models\MedicalRecord::count());
        $this->command->info('   • Diagnoses: ' . \App\Models\Diagnosis::count());
        $this->command->info('   • Treatments: ' . \App\Models\Treatment::count());
        $this->command->info('   • Invoices: ' . \App\Models\Invoice::count());
        $this->command->info('   • Payments: ' . \App\Models\Payment::count());
        $this->command->info('   • Medical Documents: ' . \App\Models\MedicalDocument::count());
        $this->command->info('');
        $this->command->info('🔑 Login credentials:');
        $this->command->info('   Admin: admin@vetcare.com / password');
        $this->command->info('   All users: password');
    }

    /**
     * Create the necessary roles for the application.
     */
    private function createRoles(): void
    {
        $roles = ['admin', 'doctor', 'user'];
        
        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'sanctum'
            ]);
        }
        
        $this->command->info('✅ Roles created: ' . implode(', ', $roles));
    }
}
