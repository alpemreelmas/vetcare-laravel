<?php

namespace App\Http\Requests\Admin;

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
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'species' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'date_of_birth' => ['nullable', Rule::date()->beforeOrEqual(now())],
            'weight' => 'nullable|numeric|min:0',
            'gender' => ['nullable', new Enum(\App\Enums\GenderEnum::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'owner_id.required' => 'The pet owner is required.',
            'owner_id.exists' => 'The selected owner does not exist.',
        ];
    }
} 