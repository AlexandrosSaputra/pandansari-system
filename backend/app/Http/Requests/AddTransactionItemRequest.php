<?php

namespace App\Http\Requests;

use App\Enums\TransactionInputMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTransactionItemRequest extends FormRequest
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
            'input_method' => ['required', Rule::enum(TransactionInputMethod::class)],
            'item_id' => ['required_if:input_method,MANUAL', 'prohibited_unless:input_method,MANUAL', 'integer', 'exists:items,id'],
            'barcode' => ['required_if:input_method,SCANNER', 'prohibited_unless:input_method,SCANNER', 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('barcode'))) {
            $this->merge(['barcode' => trim($this->input('barcode'))]);
        }
    }
}
