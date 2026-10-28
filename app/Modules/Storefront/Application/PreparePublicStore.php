<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreparePublicStore
{
    public function __construct(private readonly PublicHandle $handles) {}

    public function handle(string $tenantId, string $storeId, string $requestedHandle): StorefrontPublication
    {
        $handle = $this->handles->normalize($requestedHandle);

        return DB::transaction(function () use ($tenantId, $storeId, $handle): StorefrontPublication {
            $tenant = Tenant::query()->whereKey($tenantId)->where('status', TenantStatus::Active)
                ->lockForUpdate()->firstOrFail();
            $store = $tenant->stores()->whereKey($storeId)->where('status', StoreStatus::Active)
                ->lockForUpdate()->firstOrFail();
            $existing = StorefrontPublication::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->public_handle !== $handle) {
                    throw ValidationException::withMessages([
                        'public_handle' => 'This Store is already prepared with a different public handle.',
                    ]);
                }

                return $existing;
            }

            if (StorefrontPublication::query()->where('public_handle', $handle)->exists()) {
                throw ValidationException::withMessages([
                    'public_handle' => 'This public handle is already assigned to another Store.',
                ]);
            }

            return StorefrontPublication::query()->create([
                'tenant_id' => $tenant->getKey(),
                'store_id' => $store->getKey(),
                'public_handle' => $handle,
                'status' => StorefrontPublicationStatus::Unpublished,
                'published_at' => null,
            ]);
        });
    }
}
