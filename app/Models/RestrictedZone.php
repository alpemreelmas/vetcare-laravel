<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestrictedZone extends Model
{
    protected $table = 'doctor_restricted_time_frames';

    protected $fillable = [
        'doctor_id',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
