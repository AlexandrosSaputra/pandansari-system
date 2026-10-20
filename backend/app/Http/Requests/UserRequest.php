<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
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
        $creating = $this->isMethod('post');
        $user = $this->route('user');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null)],
            'password' => [$creating ? 'required' : 'sometimes', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'assignments' => [$creating ? 'required_without:role_id' : 'sometimes', 'array', 'min:1'],
            'assignments.*.role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'assignments.*.operational_unit_id' => ['nullable', 'integer', Rule::exists('operational_units', 'id')],
            'assignments.*.is_active' => ['sometimes', 'boolean'],
            // Kept during the transition so existing API clients can still submit one assignment.
            'role_id' => [$creating ? 'required_without:assignments' : 'sometimes', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'operational_unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_units', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
