<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Promotions\Domain\Enums\PromotionScope;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class SyncPromotionTargets
{
    /**
     * @param  array{scope: string, product_ids?: list<string>, category_ids?: list<string>, variant_ids?: list<string>}  $attributes
     */
    public function handle(Tenant $tenant, Promotion $promotion, array $attributes): void
    {
        $productIds = array_values(array_unique($attributes['product_ids'] ?? []));
        $categoryIds = array_values(array_unique($attributes['category_ids'] ?? []));
        $variantIds = array_values(array_unique($attributes['variant_ids'] ?? []));

        if ($attributes['scope'] === PromotionScope::All->value) {
            $productIds = $categoryIds = $variantIds = [];
        } elseif ($productIds === [] && $categoryIds === [] && $variantIds === []) {
            throw ValidationException::withMessages(['scope' => 'Select at least one product, category, or variant.']);
        }

        $this->assertTenantTargets($tenant, 'products', $productIds, 'product_ids');
        $this->assertTenantTargets($tenant, 'categories', $categoryIds, 'category_ids');
        $this->assertTenantTargets($tenant, 'productVariants', $variantIds, 'variant_ids');

        $pivot = fn (array $ids): array => collect($ids)->mapWithKeys(
            fn (string $id): array => [$id => ['tenant_id' => $tenant->getKey()]],
        )->all();
        $promotion->products()->sync($pivot($productIds));
        $promotion->categories()->sync($pivot($categoryIds));
        $promotion->variants()->sync($pivot($variantIds));
    }

    /** @param list<string> $ids */
    private function assertTenantTargets(Tenant $tenant, string $relation, array $ids, string $field): void
    {
        if (count($ids) !== $tenant->{$relation}()->whereKey($ids)->count()) {
            throw ValidationException::withMessages([$field => 'Every target must belong to the current company.']);
        }
    }
}
