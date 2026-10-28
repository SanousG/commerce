<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class SetPublicStorePublication
{
    public function handle(string $tenantId, string $storeId, bool $publish): StorefrontPublication
    {
        return DB::transaction(function () use ($tenantId, $storeId, $publish): StorefrontPublication {
            $tenant = Tenant::query()->whereKey($tenantId)->where('status', TenantStatus::Active)
                ->lockForUpdate()->firstOrFail();
            $store = $tenant->stores()->whereKey($storeId)->where('status', StoreStatus::Active)
                ->lockForUpdate()->firstOrFail();
            $publication = StorefrontPublication::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->firstOrFail();
            $target = $publish
                ? StorefrontPublicationStatus::Published
                : StorefrontPublicationStatus::Unpublished;

            if ($publication->status !== $target) {
                $publication->update([
                    'status' => $target,
                    'published_at' => $publish ? now() : null,
                ]);
            }

            return $publication->refresh();
        });
    }
}
