<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use App\Enums\ScannerCommand;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ItemRequest extends FormRequest
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
        $item = $this->route('item');

        return [
            'item_code' => ['prohibited'],
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::notIn([ScannerCommand::Checkout->value]),
                Rule::unique('items', 'barcode')->ignore($item instanceof Item ? $item->id : null),
            ],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => [$creating ? 'required' : 'sometimes', Rule::enum(ItemType::class)],
            'uom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visitor_factor' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $item = $this->route('item');
            $type = $this->input('type', $item instanceof Item ? $item->type->value : null);
            $visitorFactor = $this->input('visitor_factor', $item instanceof Item ? $item->visitor_factor : 0);

            if ($type === ItemType::Product->value && is_numeric($visitorFactor) && (int) $visitorFactor !== 0) {
                $validator->errors()->add('visitor_factor', 'Products must have a visitor factor of zero.');
            }
        });
    }
}
