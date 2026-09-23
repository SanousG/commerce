<?php

namespace App\Modules\Promotions\Http\Requests;

use App\Modules\Promotions\Domain\Enums\PromotionDiscountType;
use App\Modules\Promotions\Domain\Enums\PromotionScope;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SavePromotionRequest extends PromotionRequest
{
    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('discount_type');
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'internal_code' => $this->filled('internal_code')
                ? str((string) $this->input('internal_code'))->trim()->upper()->toString()
                : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'discount_value' => trim((string) $this->input('discount_value')),
            'currency' => $type === PromotionDiscountType::FixedAmount->value
                ? str((string) $this->input('currency'))->trim()->upper()->toString()
                : null,
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
            'priority' => $this->input('priority', 0),
            'product_ids' => array_values((array) $this->input('product_ids', [])),
            'category_ids' => array_values((array) $this->input('category_ids', [])),
            'variant_ids' => array_values((array) $this->input('variant_ids', [])),
        ]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:160'],
            'internal_code' => ['nullable', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in([
                PromotionStatus::Draft->value,
                PromotionStatus::Active->value,
                PromotionStatus::Inactive->value,
            ])],
            'discount_type' => ['required', Rule::enum(PromotionDiscountType::class)],
            'discount_value' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'currency' => [
                Rule::requiredIf($this->input('discount_type') === PromotionDiscountType::FixedAmount->value),
                'nullable', 'string', Rule::in(config('commerce.currencies', [])),
            ],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'priority' => ['required', 'integer', 'between:-1000000,1000000'],
            'scope' => ['required', Rule::enum(PromotionScope::class)],
            'product_ids' => ['array', 'max:100'],
            'product_ids.*' => ['uuid', 'distinct', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'category_ids' => ['array', 'max:100'],
            'category_ids.*' => ['uuid', 'distinct', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'variant_ids' => ['array', 'max:100'],
            'variant_ids.*' => ['uuid', 'distinct', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->has('discount_value')) {
                $value = BigDecimal::of($this->input('discount_value'));
                if (! $value->isPositive()) {
                    $validator->errors()->add('discount_value', 'The discount value must be greater than zero.');
                } elseif ($this->input('discount_type') === PromotionDiscountType::Percentage->value
                    && $value->isGreaterThan(100)) {
                    $validator->errors()->add('discount_value', 'A percentage discount cannot exceed 100.');
                }
            }

            if ($this->input('scope') === PromotionScope::Selected->value
                && count($this->input('product_ids', [])) + count($this->input('category_ids', [])) + count($this->input('variant_ids', [])) === 0) {
                $validator->errors()->add('scope', 'Select at least one product, category, or variant.');
            }

            $code = $this->input('internal_code');
            if ($code !== null && ! $validator->errors()->has('internal_code')) {
                $query = app(TenantContext::class)->get()->promotions()->whereRaw('upper(internal_code) = ?', [$code]);
                $promotionId = $this->route('promotion');
                if (is_string($promotionId)) {
                    $query->whereKeyNot($promotionId);
                }
                if ($query->exists()) {
                    $validator->errors()->add('internal_code', 'The internal code has already been taken.');
                }
            }
        });
    }
}
