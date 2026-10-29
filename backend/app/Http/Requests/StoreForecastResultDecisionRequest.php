<?php

namespace App\Http\Requests;

use App\Enums\ForecastDecisionStatus;
use App\Models\ForecastResult;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreForecastResultDecisionRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in([
                    ForecastDecisionStatus::Approved->value,
                    ForecastDecisionStatus::Rejected->value,
                ]),
            ],
            'final_restock_quantity' => [
                'nullable',
                'required_if:status,'.ForecastDecisionStatus::Approved->value,
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'reason' => [
                'nullable',
                'required_if:status,'.ForecastDecisionStatus::Rejected->value,
                'string',
                'max:2000',
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['status', 'final_restock_quantity', 'reason'])) {
                    return;
                }

                $result = $this->route('forecastResult');
                $isCorrectedApproval = $this->input('status') === ForecastDecisionStatus::Approved->value
                    && $result instanceof ForecastResult
                    && $this->integer('final_restock_quantity') !== $result->recommended_restock;

                if ($isCorrectedApproval && $this->input('reason') === null) {
                    $validator->errors()->add('reason', 'Alasan wajib diisi ketika jumlah restock dikoreksi.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $reason = trim($this->input('reason'));
            $this->merge(['reason' => $reason === '' ? null : $reason]);
        }
    }
}
