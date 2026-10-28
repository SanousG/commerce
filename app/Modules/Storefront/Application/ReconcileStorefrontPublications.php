<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

final class ReconcileStorefrontPublications
{
    public function __construct(private readonly ProvisionStorefrontPublication $provision) {}

    public function handle(?Tenant $tenant = null): int
    {
        $count = 0;

        Store::query()
            ->when($tenant !== null, fn (Builder $query) => $query->where('tenant_id', $tenant->getKey()))
            ->whereDoesntHave('storefrontPublication')
            ->orderBy('id')
            ->lazyById()
            ->each(function (Store $store) use (&$count): void {
                if ($this->provision->handle($store)->wasRecentlyCreated) {
                    $count++;
                }
            });

        return $count;
    }
}
