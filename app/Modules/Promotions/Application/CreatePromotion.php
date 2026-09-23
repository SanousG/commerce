<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreatePromotion
{
    public function __construct(private readonly SyncPromotionTargets $targets) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, array $attributes): Promotion
    {
        return DB::transaction(function () use ($tenant, $attributes): Promotion {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $promotion = $lockedTenant->promotions()->create($this->fields($attributes));
            $this->targets->handle($lockedTenant, $promotion, $attributes);

            return $promotion->load(['products:id', 'categories:id', 'variants:id']);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function fields(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'internal_code' => $attributes['internal_code'] ?? null,
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'],
            'discount_type' => $attributes['discount_type'],
            'discount_value' => $attributes['discount_value'],
            'currency' => $attributes['currency'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'priority' => $attributes['priority'],
            'scope' => $attributes['scope'],
        ];
    }
}
