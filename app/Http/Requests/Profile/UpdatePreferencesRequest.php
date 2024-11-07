<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdatePreferencesRequest extends FormRequest
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
        return [
            'preferences' => ['required', 'array'],
            'preferences.theme' => ['sometimes', 'string', 'in:light,dark,system'],
            'preferences.notifications' => ['sometimes', 'array'],
            'preferences.notifications.*' => ['boolean'],
            'preferences.language' => ['sometimes', 'string', 'in:' . implode(',', config('app.available_locales', ['en']))],
            'preferences.timezone' => ['sometimes', 'string', 'timezone'],
            'preferences.date_format' => ['sometimes', 'string'],
            'preferences.time_format' => ['sometimes', 'string', 'in:12,24'],
            'preferences.first_day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'preferences.dashboard_layout' => ['sometimes', 'array'],
            'preferences.dashboard_layout.*' => ['string'],
            'preferences.email_notifications' => ['sometimes', 'array'],
            'preferences.email_notifications.*' => ['boolean'],
            'preferences.push_notifications' => ['sometimes', 'array'],
            'preferences.push_notifications.*' => ['boolean'],
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
            'preferences.theme' => 'theme',
            'preferences.notifications' => 'notification settings',
            'preferences.language' => 'language',
            'preferences.timezone' => 'timezone',
            'preferences.date_format' => 'date format',
            'preferences.time_format' => 'time format',
            'preferences.first_day_of_week' => 'first day of week',
            'preferences.dashboard_layout' => 'dashboard layout',
            'preferences.email_notifications' => 'email notification settings',
            'preferences.push_notifications' => 'push notification settings',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferences.theme.in' => 'The selected theme is invalid.',
            'preferences.language.in' => 'The selected language is not supported.',
            'preferences.timezone.timezone' => 'The selected timezone is invalid.',
            'preferences.time_format.in' => 'The time format must be either 12 or 24 hour.',
            'preferences.first_day_of_week.between' => 'The first day of week must be between 0 (Sunday) and 6 (Saturday).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure preferences is an array
        if (!is_array($this->preferences)) {
            $this->merge(['preferences' => []]);
        }

        // Set default values if not provided
        $defaults = [
            'theme' => 'system',
            'language' => config('app.locale', 'en'),
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'Y-m-d',
            'time_format' => '24',
            'first_day_of_week' => 0,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($this->preferences[$key])) {
                $this->preferences[$key] = $value;
            }
        }
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Log the preferences update
        activity()
            ->causedBy($this->user())
            ->forTenant($this->user()->tenant)
            ->withProperties([
                'old_preferences' => $this->user()->preferences,
                'new_preferences' => $this->preferences,
            ])
            ->log('User preferences updated');
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        // Log the failed preferences update
        activity()
            ->causedBy($this->user())
            ->forTenant($this->user()->tenant)
            ->withProperties([
                'errors' => $validator->errors()->toArray(),
                'attempted_preferences' => $this->preferences,
            ])
            ->log('User preferences update failed validation');

        parent::failedValidation($validator);
    }
}
