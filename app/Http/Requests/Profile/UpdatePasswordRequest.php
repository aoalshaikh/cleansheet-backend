<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $passwordPolicy = $this->user()->tenant->getSetting('security.password_policy', 'default');
        $passwordRules = $this->getPasswordRules($passwordPolicy);

        return [
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(['required', 'string', 'confirmed'], $passwordRules),
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'current password',
            'password' => 'new password',
            'password_confirmation' => 'password confirmation',
        ];
    }

    /**
     * Get password rules based on policy.
     *
     * @param string $policy
     * @return array<int, \Illuminate\Validation\Rules\Password>
     */
    protected function getPasswordRules(string $policy): array
    {
        $rules = match ($policy) {
            'strict' => Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
            'default' => Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers(),
            default => Password::min(8)
                ->letters()
                ->numbers(),
        };

        return [$rules];
    }
}
