<?php

namespace App\Modules\Analytics\Http\Requests;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'string', Rule::enum(DashboardRange::class)],
            'from' => ['nullable', 'required_if:range,'.DashboardRange::Custom->value, 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:range,'.DashboardRange::Custom->value, 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('range') !== DashboardRange::Custom->value || $validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('from'), 'UTC');
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('to'), 'UTC');

            if ($from === false || $to === false) {
                return;
            }

            if ($from->diffInDays($to) + 1 > DashboardDateRange::MAX_CUSTOM_DAYS) {
                $validator->errors()->add(
                    'to',
                    __('The custom range must not exceed :days days.', ['days' => DashboardDateRange::MAX_CUSTOM_DAYS]),
                );
            }
        });
    }
}
