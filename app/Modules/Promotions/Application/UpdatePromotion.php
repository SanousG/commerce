<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePromotion
{
    public function __construct(private readonly SyncPromotionTargets $targets) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, Promotion $promotion, array $attributes): Promotion
    {
        return DB::transaction(function () use ($tenant, $promotion, $attributes): Promotion {
            $scoped = $tenant->promotions()->lockForUpdate()->findOrFail($promotion->getKey());
            if ($scoped->status === PromotionStatus::Archived) {
                throw ValidationException::withMessages(['status' => 'Archived promotions cannot be changed.']);
            }

            $scoped->update([
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
            ]);
            $this->targets->handle($tenant, $scoped, $attributes);

            return $scoped->load(['products:id', 'categories:id', 'variants:id']);
        });
    }
}
