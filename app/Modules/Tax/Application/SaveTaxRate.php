<?php

namespace App\Modules\Tax\Application;

use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTaxRate
{
    public function create(Tenant $tenant, array $data): TaxRate
    {
        return $this->persist($tenant, null, $data);
    }

    public function update(TaxRate $rate, array $data): TaxRate
    {
        return $this->persist($rate->tenant()->firstOrFail(), $rate, $data);
    }

    private function persist(Tenant $tenant, ?TaxRate $rate, array $data): TaxRate
    {
        return DB::transaction(function () use ($tenant, $rate, $data): TaxRate {
            $categoryIds = collect([$data['tax_category_id'], $rate?->tax_category_id])->filter()->unique()->sort()->values();
            $categories = TaxCategory::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $category = $categories->get($data['tax_category_id']);
            if ($category === null) {
                throw ValidationException::withMessages(['tax_category_id' => 'The tax category must belong to the current company.']);
            }
            if ($category->code !== TaxCategoryCode::Standard && ! BigDecimal::of($data['rate'])->isZero()) {
                throw ValidationException::withMessages(['rate' => 'Exempt and unaffected categories require a zero rate.']);
            }

            if ($data['status'] === TaxCategoryStatus::Active->value) {
                $overlap = TaxRate::query()->where('tenant_id', $tenant->getKey())
                    ->where('tax_category_id', $category->getKey())
                    ->where('status', TaxCategoryStatus::Active->value)
                    ->when($rate, fn ($query) => $query->whereKeyNot($rate->getKey()))
                    ->where(fn ($query) => $query->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', $data['valid_until'] ?? '9999-12-31'))
                    ->where(fn ($query) => $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $data['valid_from'] ?? '0001-01-01'))
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['valid_from' => 'Active tax-rate periods cannot overlap.']);
                }
            }

            if ($rate === null) {
                return $tenant->taxRates()->create($data);
            }

            $lockedRate = $tenant->taxRates()->lockForUpdate()->findOrFail($rate->getKey());
            $lockedRate->update($data);

            return $lockedRate->refresh();
        });
    }
}
