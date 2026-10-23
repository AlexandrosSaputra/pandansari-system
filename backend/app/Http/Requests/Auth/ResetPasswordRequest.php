<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'max:50'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[A-HJ-NP-Z2-9]+$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.required' => 'Masukkan username.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'otp.required' => 'Masukkan kode OTP.',
            'otp.size' => 'Kode OTP harus terdiri dari 6 karakter.',
            'otp.regex' => 'Kode OTP hanya boleh berisi huruf kapital dan angka yang tersedia.',
            'password.required' => 'Masukkan kata sandi baru.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');
        $otp = $this->input('otp');

        if (is_string($username)) {
            $this->merge(['username' => Str::lower(trim($username))]);
        }

        if (is_string($otp)) {
            $this->merge(['otp' => Str::upper(trim($otp))]);
        }
    }
}
