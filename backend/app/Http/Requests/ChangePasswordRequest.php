<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }
}
