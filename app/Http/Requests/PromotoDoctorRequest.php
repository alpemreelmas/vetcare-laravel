<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotoDoctorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'specialization' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string'],
            'biography' => ['nullable', 'string'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
        ];
    }
}
