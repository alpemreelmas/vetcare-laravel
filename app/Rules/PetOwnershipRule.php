<?php

namespace App\Rules;

use App\Models\Pet;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PetOwnershipRule implements ValidationRule
{
    public function __construct(private int $userId)
    {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pet = Pet::where('id', $value)->where('owner_id', $this->userId)->first();
        
        if (!$pet) {
            $fail('The selected pet does not belong to you.');
        }
    }
} 