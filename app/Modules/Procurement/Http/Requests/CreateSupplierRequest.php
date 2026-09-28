<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedSupplierData());
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('code')) {
                return;
            }

            $query = Supplier::query()->where('tenant_id', app(TenantContext::class)->id())
                ->whereRaw('upper(code) = ?', [$this->input('code')]);
            $supplier = $this->route('supplier');
            if (is_string($supplier)) {
                $query->whereKeyNot($supplier);
            }
            if ($query->exists()) {
                $validator->errors()->add('code', 'The supplier code has already been taken.');
            }
        });
    }

    protected function normalizedSupplierData(): array
    {
        return [
            'code' => str((string) $this->input('code'))->trim()->upper()->toString(),
            'name' => trim((string) $this->input('name')),
            'contact_name' => $this->nullableTrimmed('contact_name'),
            'email' => $this->nullableTrimmed('email'),
            'phone' => $this->nullableTrimmed('phone'),
            'website' => $this->nullableTrimmed('website'),
            'notes' => $this->nullableTrimmed('notes'),
        ];
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
