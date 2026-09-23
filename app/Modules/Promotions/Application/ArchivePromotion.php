<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ArchivePromotion
{
    public function handle(Tenant $tenant, Promotion $promotion): Promotion
    {
        return DB::transaction(function () use ($tenant, $promotion): Promotion {
            $scoped = $tenant->promotions()->lockForUpdate()->findOrFail($promotion->getKey());
            $scoped->update(['status' => PromotionStatus::Archived]);

            return $scoped;
        });
    }
}
