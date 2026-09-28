<?php

namespace App\Modules\Tax\Application;

use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ResolveEffectiveTaxRate
{
    public function handle(TaxCategory $category, CarbonInterface $operationDate): TaxRate
    {
        if ($category->status !== TaxCategoryStatus::Active) {
            throw ValidationException::withMessages(['tax_category' => 'The assigned tax category is inactive.']);
        }

        $rates = $category->rates()->where('tenant_id', $category->tenant_id)
            ->where('status', TaxCategoryStatus::Active->value)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $operationDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $operationDate->toDateString()))
            ->sharedLock()->get();

        if ($rates->count() !== 1) {
            throw ValidationException::withMessages([
                'tax_rate' => $rates->isEmpty()
                    ? "No active tax rate applies to category {$category->code->value} on the issue date."
                    : "More than one active tax rate applies to category {$category->code->value} on the issue date.",
            ]);
        }

        return $rates->first();
    }
}
