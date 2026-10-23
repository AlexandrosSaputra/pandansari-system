<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($this->user()?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => Str::lower(trim($username))]);
        }
    }
}
