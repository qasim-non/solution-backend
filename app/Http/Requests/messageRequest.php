<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class messageRequest extends FormRequest
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
            'full_name.required' => 'The full name is required.',
            'full_name.string' => 'The full name must be a valid text value.',
            'full_name.max' => 'The full name may not exceed 255 characters.',

            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',

            'text_message.required' => 'The message is required.',
            'text_message.string' => 'The message must be a valid text value.',
            'text_message.max' => 'The message may not exceed 3000 characters.',
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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'text_message' => ['required', 'string', 'max:3000'],
        ];
    }
}
