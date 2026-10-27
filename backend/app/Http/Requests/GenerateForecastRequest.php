<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateForecastRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('operational_units', 'id')->where(fn ($query) => $query
                    ->where('type', 'OUTLET')
                    ->where('is_active', true)),
            ],
            'horizon' => ['required', Rule::in(['next_week', 'next_month', 'custom'])],
            'target_date' => [
                'bail',
                'nullable',
                'required_if:horizon,custom',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('horizon') !== 'custom' || ! is_string($value)) {
                        return;
                    }

                    $today = CarbonImmutable::today(config('forecast.timezone'));
                    $target = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('forecast.timezone'));

                    if ($target === false || ! $target->greaterThan($today)) {
                        $fail('Tanggal target harus setelah hari ini.');
                    } elseif ($target->greaterThan($today->addYear())) {
                        $fail('Tanggal target maksimal satu tahun dari hari ini.');
                    }
                },
            ],
        ];
    }
}
