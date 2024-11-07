<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'nullable|date',
            'metadata' => 'nullable|array',
            'metadata.position' => 'nullable|string|in:goalkeeper,defender,midfielder,forward',
            'metadata.preferred_foot' => 'nullable|string|in:left,right,both',
            'metadata.height' => 'nullable|numeric|min:100|max:250',
            'metadata.weight' => 'nullable|numeric|min:30|max:150'
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
            'phone.unique' => 'This phone number is already registered',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'metadata.position.in' => 'Invalid player position',
            'metadata.preferred_foot.in' => 'Invalid preferred foot selection',
            'metadata.height.min' => 'Height must be at least 100 cm',
            'metadata.height.max' => 'Height cannot exceed 250 cm',
            'metadata.weight.min' => 'Weight must be at least 30 kg',
            'metadata.weight.max' => 'Weight cannot exceed 150 kg'
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'phone' => 'phone number',
            'date_of_birth' => 'date of birth',
            'metadata.position' => 'position',
            'metadata.preferred_foot' => 'preferred foot',
            'metadata.height' => 'height',
            'metadata.weight' => 'weight'
        ];
    }
}
