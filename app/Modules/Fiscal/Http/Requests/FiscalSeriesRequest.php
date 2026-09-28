<?php

namespace App\Modules\Fiscal\Http\Requests;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FiscalSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fiscal_settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => str((string) $this->input('document_type'))->trim()->upper()->toString(),
            'series' => str((string) $this->input('series'))->trim()->upper()->toString(),
            'status' => str((string) $this->input('status'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        $seriesId = (string) $this->route('fiscalSeries');

        return [
            'tenant_id' => ['prohibited'],
            'store_id' => ['prohibited'],
            'next_number' => ['prohibited'],
            'document_type' => ['required', Rule::enum(FiscalDocumentType::class)],
            'series' => [
                'required',
                'string',
                'max:40',
                Rule::unique('fiscal_series', 'series')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', app(TenantContext::class)->id())
                        ->where('document_type', $this->input('document_type')))
                    ->ignore($seriesId),
            ],
            'number_padding' => ['required', 'integer', 'between:1,20'],
            'status' => ['required', Rule::enum(FiscalConfigurationStatus::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['document_type', 'status'])
                || $this->input('status') !== FiscalConfigurationStatus::Active->value) {
                return;
            }

            $conflict = FiscalSeries::query()
                ->where('tenant_id', app(TenantContext::class)->id())
                ->where('store_id', app(StoreContext::class)->id())
                ->where('document_type', $this->input('document_type'))
                ->where('status', FiscalConfigurationStatus::Active->value)
                ->when($this->route('fiscalSeries'), fn ($query) => $query->whereKeyNot((string) $this->route('fiscalSeries')))
                ->exists();

            if ($conflict) {
                $validator->errors()->add('status', 'An active series already exists for this store and document type.');
            }
        }];
    }
}
