<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\PromotoDoctorRequest;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        return ResponseHelper::success(
            message: 'Doctors fetched successfully',
            data: Doctor::all(),
        );
    }

    public function show(Doctor $doctor)
    {
        return ResponseHelper::success(
            message: 'Doctor fetched successfully',
            data: $doctor,
        );
    }

    public function promoteToDoctor(User $user, PromotoDoctorRequest $request)
    {
        $role = Role::where('name', 'doctor')->firstOrFail();

        $user->assignRole($role);

        $profile = Doctor::create([
            'user_id' => $user->id,
            'specialization' => $request->specialization,
            'license_number' => $request->license_number,
            'phone_number' => $request->phone_number,
            'biography' => $request->biography,
            'working_hours' => $request->start_time . '-' . $request->end_time,
        ]);

        return ResponseHelper::success(
            message: 'Doctor promoted successfully',
            data: $profile,
        );
    }

}
