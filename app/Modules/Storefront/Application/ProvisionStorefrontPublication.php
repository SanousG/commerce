<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class ProvisionStorefrontPublication
{
    private const HANDLE_LOCK = 'storefront-public-handle-allocation';

    public function __construct(private readonly PublicHandle $handles) {}

    public function handle(Store $store): StorefrontPublication
    {
        return DB::transaction(function () use ($store): StorefrontPublication {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [self::HANDLE_LOCK]);

            $lockedStore = Store::query()->lockForUpdate()->findOrFail($store->getKey());

            if ((string) $lockedStore->tenant_id !== (string) $store->tenant_id) {
                throw new LogicException('The Store Tenant changed while provisioning its Storefront.');
            }

            $existing = StorefrontPublication::query()
                ->where('tenant_id', $lockedStore->tenant_id)
                ->where('store_id', $lockedStore->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return StorefrontPublication::query()->create([
                'tenant_id' => $lockedStore->tenant_id,
                'store_id' => $lockedStore->getKey(),
                'public_handle' => $this->nextHandle($lockedStore),
                'status' => StorefrontPublicationStatus::Unpublished,
                'published_at' => null,
            ]);
        });
    }

    private function nextHandle(Store $store): string
    {
        $base = Str::slug($store->name);

        if ($base === '' || in_array($base, config('storefront.reserved_handles', []), true)) {
            $base = 'store-'.$store->getKey();
        }

        $base = rtrim(substr($base, 0, PublicHandle::MAX_LENGTH), '-');
        $candidate = $this->handles->normalize($base);
        $suffix = 2;

        while (StorefrontPublication::query()->where('public_handle', $candidate)->exists()) {
            $tail = '-'.$suffix++;
            $candidate = rtrim(substr($base, 0, PublicHandle::MAX_LENGTH - strlen($tail)), '-').$tail;
        }

        return $candidate;
    }
}
