<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PersonName;
use App\Support\AccountEmailGuard;
use App\Support\NameNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => NameNormalizer::clean($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new PersonName()],
            'email' => [
                new \App\Rules\SeparateAccountEmail('logistics', $this->user()->id),
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                function ($attribute, $value, $fail) {
                    if ($message = AccountEmailGuard::conflict((string) $value, ['user_id' => $this->user()->id])) {
                        $fail($message);
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
