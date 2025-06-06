<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorRestrictedTimeFrame;
use Illuminate\Database\Seeder;

class DoctorRestrictedTimeFrameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = Doctor::all();
        
        if ($doctors->isNotEmpty()) {
            // Create restricted time frames for 60% of doctors
            $doctorsToRestrict = $doctors->random(ceil($doctors->count() * 0.6));
            
            $doctorsToRestrict->each(function ($doctor) {
                // Each doctor gets 1-4 restricted time frames
                $count = rand(1, 4);
                DoctorRestrictedTimeFrame::factory()->count($count)->create([
                    'doctor_id' => $doctor->id,
                ]);
            });
        } else {
            // Fallback: create with factory relationships
            DoctorRestrictedTimeFrame::factory()->count(15)->create();
        }
    }
} 