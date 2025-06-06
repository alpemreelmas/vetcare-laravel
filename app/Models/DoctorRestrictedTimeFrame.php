<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoctorRestrictedTimeFrame extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
