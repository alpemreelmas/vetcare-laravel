<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('pets', 'name')->whereNotIn('id', [$this->route('pet')->id])],
            'species' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'date_of_birth' => ['nullable', Rule::date()->beforeOrEqual(now())],
            'weight' => 'nullable|numeric|min:0',
            'gender' => ['nullable', new Enum(\App\Enums\GenderEnum::class)],
        ];
    }
}
