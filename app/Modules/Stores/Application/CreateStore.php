<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStore
{
    /**
     * @param  array{name: string, currency: string, timezone: string, locale: string}  $attributes
     */
    public function handle(Tenant $tenant, array $attributes): StoreCreationResult
    {
        return DB::transaction(function () use ($tenant, $attributes): StoreCreationResult {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $isFirstStore = ! $lockedTenant->stores()->exists();

            $store = $lockedTenant->stores()->create([
                ...$attributes,
                'slug' => $this->uniqueSlug($lockedTenant, $attributes['name']),
                'status' => StoreStatus::Active,
            ]);
            $store->settings()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'country_code' => 'PE',
                'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS,
                'primary_color' => '#17211B',
            ]);

            return new StoreCreationResult($store, $isFirstStore);
        });
    }

    private function uniqueSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $suffix = 2;

        while ($tenant->stores()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
