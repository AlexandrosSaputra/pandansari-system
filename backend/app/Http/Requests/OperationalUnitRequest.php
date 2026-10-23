<?php

namespace App\Http\Requests;

use App\Enums\OperationalUnitType;
use App\Models\OperationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OperationalUnitRequest extends FormRequest
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

        return [
            'code' => $creating
                ? ['sometimes', 'string', 'regex:/^[A-Z]{2}\d{4}$/', Rule::unique('operational_units', 'code')]
                : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => [$creating ? 'required' : 'sometimes', Rule::enum(OperationalUnitType::class)],
            'scanner_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unit = $this->route('operationalUnit');
            $type = $this->input('type', $unit instanceof OperationalUnit ? $unit->type->value : null);
            $scannerEnabled = $this->has('scanner_enabled')
                ? $this->boolean('scanner_enabled')
                : ($unit instanceof OperationalUnit && $unit->scanner_enabled);

            if ($type === OperationalUnitType::Ticketing->value && $scannerEnabled) {
                $validator->errors()->add('scanner_enabled', 'Ticketing does not support scanners.');
            }
        });
    }
}
