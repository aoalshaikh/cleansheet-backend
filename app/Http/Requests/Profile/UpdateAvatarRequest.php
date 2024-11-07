<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateAvatarRequest extends FormRequest
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
        $maxSize = $this->user()->tenant->getSetting('capabilities.max_avatar_size', 2048);

        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:' . $maxSize,
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
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
            'avatar' => 'profile picture',
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
            'avatar.max' => 'The profile picture must not be larger than :max kilobytes.',
            'avatar.dimensions' => 'The profile picture must be between 100x100 and 2000x2000 pixels.',
            'avatar.mimes' => 'The profile picture must be a file of type: jpeg, png, jpg, gif.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->hasFile('avatar')) {
            $this->merge([
                'avatar_original_name' => $this->file('avatar')->getClientOriginalName(),
            ]);
        }
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Log the avatar update attempt
        activity()
            ->causedBy($this->user())
            ->forTenant($this->user()->tenant)
            ->withProperties([
                'original_name' => $this->avatar_original_name ?? null,
                'size' => $this->file('avatar')->getSize(),
                'mime_type' => $this->file('avatar')->getMimeType(),
            ])
            ->log('Avatar update attempted');
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        // Log the failed avatar update attempt
        activity()
            ->causedBy($this->user())
            ->forTenant($this->user()->tenant)
            ->withProperties([
                'errors' => $validator->errors()->toArray(),
                'original_name' => $this->avatar_original_name ?? null,
            ])
            ->log('Avatar update failed validation');

        parent::failedValidation($validator);
    }
}
