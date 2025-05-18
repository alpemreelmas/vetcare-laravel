<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'user_id',
        'pet_id',
        'start_datetime',
        'end_datetime',
        'appointment_type',
        'duration',
        'notes',
        'status',
    ];
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'duration' => 'integer',
    ];
}
