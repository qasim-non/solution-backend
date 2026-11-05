<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class loginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Custom validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'The username is required.',
            'username.string' => 'The username must be a valid text value.',
            'username.max' => 'The username may not exceed 255 characters.',

            'password.required' => 'The password is required.',
            'password.string' => 'The password must be a valid text value.',
            'password.max' => 'The password may not exceed 255 characters.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required','string', 'max:255'],
            'password' => ['required','string', 'max:255'],
        ];
    }
}
