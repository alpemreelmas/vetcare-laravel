<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Http\Requests\PromotoDoctorRequest;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

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

    public function store(PromotoDoctorRequest $request)
    {
        $role = Role::where('name', 'doctor')->firstOrFail();

        $user = User::where('email', $request->email)->first();

        if ($user) {
            return ResponseHelper::error(
                message: 'User already exist.',
                status: Response::HTTP_BAD_REQUEST,
            );
        }

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $newUser->assignRole($role);

        Doctor::create([
            'user_id' => $newUser->id,
            'specialization' => $request->specialization,
            'license_number' => $request->license_number,
            'phone_number' => $request->phone_number,
            'biography' => $request->biography,
            'working_hours' => $request->start_time . '-' . $request->end_time,
        ]);

        return ResponseHelper::success(
            message: 'Doctor created successfully',
            data: $newUser->load('doctor'),
        );
    }

    public function update()
    {

    }

    public function destroy()
    {

    }

}
