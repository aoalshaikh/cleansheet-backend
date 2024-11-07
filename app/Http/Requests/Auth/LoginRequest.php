<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required_without:phone|email|exists:users,email',
            'phone' => 'required_without:email|string|exists:users,phone',
            'password' => 'required|string|min:8',
            'remember' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'The email field is required when phone is not present.',
            'phone.required_without' => 'The phone field is required when email is not present.',
            'email.exists' => 'These credentials do not match our records.',
            'phone.exists' => 'These credentials do not match our records.',
        ];
    }
}
