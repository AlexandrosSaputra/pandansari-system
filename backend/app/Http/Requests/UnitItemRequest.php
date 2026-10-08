<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UnitItemRequest extends FormRequest
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
        $unit = $this->route('operationalUnit');

        return [
            'item_id' => $creating
                ? ['required', 'integer', Rule::exists('items', 'id'), Rule::unique('unit_items', 'item_id')->where('operational_unit_id', $unit instanceof OperationalUnit ? $unit->id : null)]
                : ['prohibited'],
            'selling_price' => [$creating ? 'required' : 'sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'minimum_stock' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unitItem = $this->route('unitItem');
            $item = $unitItem instanceof UnitItem ? $unitItem->item : Item::find($this->input('item_id'));
            $minimumStock = $this->input('minimum_stock', $unitItem instanceof UnitItem ? $unitItem->minimum_stock : 0);

            if ($item?->type === ItemType::Service && is_numeric($minimumStock) && (int) $minimumStock !== 0) {
                $validator->errors()->add('minimum_stock', 'Services must have a minimum stock of zero.');
            }
        });
    }
}
