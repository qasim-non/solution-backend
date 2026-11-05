<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class projectRequestsRequest extends FormRequest
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
            'search.string' => 'The search term must be a valid text value.',
            'search.max' => 'The search term may not exceed 50 characters.',

            'system_type.integer' => 'The system type must be a valid number.',
            'system_type.exists' => 'The selected system type is invalid.',

            'status.string' => 'The status must be a valid text value.',
            'status.in' => 'The status must be either pending or completed.',

            'start_date.required_with' => 'The start date is required when an end date is provided.',
            'start_date.date_format' => 'The start date must be in the format YYYY-MM-DD.',

            'end_date.required_with' => 'The end date is required when a start date is provided.',
            'end_date.date_format' => 'The end date must be in the format YYYY-MM-DD.',
            'end_date.after_or_equal' => 'The end date must be the same as or later than the start date.',
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
            'search' => ['nullable', 'string', 'max:50'],
            'system_type' => ['nullable', 'integer' , 'exists:system_types,id'],
            'status' => ['nullable', 'string', 'in:pending,completed'],

            // If start_date is present, end_date must be present too
            'start_date' => [
                'nullable',
                'required_with:end_date',
                'date_format:Y-m-d'
            ],

            // If end_date is present, start_date must be present too
            'end_date'   => [
                'nullable',
                'required_with:start_date',
                'date_format:Y-m-d',
                'after_or_equal:start_date' // Ensures end_date is chronologically after start_date
            ],
        ];
    }
}
