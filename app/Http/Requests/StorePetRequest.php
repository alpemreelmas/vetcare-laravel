<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return !auth()->guest();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'species' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'date_of_birth' => ['nullable', Rule::date()->beforeOrEqual(now())],
            'weight' => 'nullable|numeric|min:0|max:999.99',
            'gender' => ['nullable', new Enum(\App\Enums\GenderEnum::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'The date of birth cannot be in the future.',
            'weight.max' => 'The weight cannot exceed 999.99 kg.',
        ];
    }
}
