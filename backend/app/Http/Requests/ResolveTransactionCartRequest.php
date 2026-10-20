<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveTransactionCartRequest extends FormRequest
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
            'operational_unit_id' => [
                $this->user()?->activeRole()?->slug === 'superadmin' ? 'required' : ($this->user()?->activeRole()?->slug === 'admin' ? 'prohibited' : 'sometimes'),
                'integer',
                Rule::exists('operational_units', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
        ];
    }
}
