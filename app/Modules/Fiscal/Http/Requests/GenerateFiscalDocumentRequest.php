<?php

namespace App\Modules\Fiscal\Http\Requests;

use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fiscal_documents.issue') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => str((string) $this->input('document_type'))->trim()->upper()->toString(),
            'customer_document_type' => $this->nullableUpper('customer_document_type'),
            'customer_document_number' => $this->nullableTrimmed('customer_document_number'),
            'customer_name' => $this->nullableTrimmed('customer_name'),
            'customer_address' => $this->nullableTrimmed('customer_address'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'], 'store_id' => ['prohibited'], 'order_id' => ['prohibited'],
            'taxable_amount' => ['prohibited'], 'tax_amount' => ['prohibited'], 'grand_total' => ['prohibited'],
            'document_type' => ['required', Rule::in([
                FiscalDocumentType::Invoice->value, FiscalDocumentType::Receipt->value,
            ])],
            'customer_document_type' => ['nullable', 'string', 'max:32', 'required_with:customer_document_number'],
            'customer_document_number' => ['nullable', 'string', 'max:80', 'required_with:customer_document_type'],
            'customer_name' => ['nullable', 'string', 'max:200'],
            'customer_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('document_type') !== FiscalDocumentType::Invoice->value) {
                return;
            }
            foreach (['customer_document_number', 'customer_name', 'customer_address'] as $field) {
                if ($this->input($field) === null) {
                    $validator->errors()->add($field, 'This field is required for Factura.');
                }
            }
            if ($this->input('customer_document_type') !== 'RUC') {
                $validator->errors()->add('customer_document_type', 'Factura requires the RUC document type.');
            }
        }];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableUpper(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtoupper($value);
    }
}
