<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->routeIs('verify-otp')) {
            return [
                'type' => 'required|string|in:email,phone',
                'identifier' => 'required|string',
                'code' => 'required|string|size:6'
            ];
        }

        return [
            'type' => 'required|string|in:email,phone',
            'identifier' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The type must be either email or phone.',
            'code.size' => 'The code must be exactly 6 characters.',
        ];
    }
}
