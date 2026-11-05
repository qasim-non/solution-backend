<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class projectRequest extends FormRequest
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
            'project_name.required' => 'The project name is required.',
            'project_name.string' => 'The project name must be a valid text value.',
            'project_name.max' => 'The project name may not exceed 100 characters.',

            'mobile.required' => 'The mobile number is required.',
            'mobile.string' => 'The mobile number must be a valid text value.',
            'mobile.max' => 'The mobile number may not exceed 20 characters.',

            'description.string' => 'The description must be a valid text value.',
            'description.max' => 'The description may not exceed 1000 characters.',

            'system_types.required' => 'Please select at least one system type.',
            'system_types.array' => 'The system types must be provided as a list.',
            'system_types.min' => 'Please select at least one system type.',
            'system_types.*.integer' => 'Each selected system type must be a valid number.',
            'system_types.*.exists' => 'One or more selected system types are invalid.',

            'social_media.required' => 'Please select at least one social media platform.',
            'social_media.array' => 'The social media platforms must be provided as a list.',
            'social_media.min' => 'Please select at least one social media platform.',
            'social_media.*.integer' => 'Each selected social media platform must be a valid number.',
            'social_media.*.exists' => 'One or more selected social media platforms are invalid.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation()
    {
        // Extract the JSON keys into a temporary field if they exist
        if ($this->has('social_media') && is_array($this->social_media)) {
            $this->merge([
                'social_media_keys' => array_keys($this->social_media),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'project_name' => ['required', 'string', 'min:3', 'max:100'],
            'mobile' => ['required', 'string', 'min:10', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],

            'system_types' => ['required', 'array', 'min:1'],
            'system_types.*' => ['integer', 'exists:system_types,id'],

            'social_media_keys.*' => ['exists:social_media_platforms,id'],
            // Social media links are optional (the form labels them as optional).
            'social_media' => ['nullable', 'array'],
            'social_media.*' => ['string', 'max:255'],
        ];
    }
}
