<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStockTransferRequest extends FormRequest
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
            'destination_operational_unit_id' => [
                'required',
                'integer',
                Rule::exists('operational_units', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('items', 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('type', ItemType::Product->value)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
