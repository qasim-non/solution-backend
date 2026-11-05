<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'device_id' => ['required', 'string', 'max:64'],
            'platform' => ['nullable', 'string', 'in:web,android,ios'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'The FCM token is required.',
            'device_id.required' => 'The device id is required.',
            'platform.in' => 'The platform must be one of: web, android, ios.',
        ];
    }
}
