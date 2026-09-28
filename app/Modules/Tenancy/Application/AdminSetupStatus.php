<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;

class AdminSetupStatus
{
    /** @return array<string, array{status: string, detail: string}> */
    public function for(Tenant $tenant, ?Store $store): array
    {
        $hasActiveStore = $tenant->stores()->where('status', StoreStatus::Active)->exists();
        $hasDefaultInventory = $tenant->inventoryLocations()->where('is_default', true)->exists();
        $hasDefaultPriceList = $tenant->priceLists()->where('is_default', true)->exists();
        $hasIssuer = $tenant->fiscalIssuerProfile()->where('status', FiscalConfigurationStatus::Active)->exists();
        $hasSeries = $store !== null && $tenant->fiscalSeries()
            ->where('store_id', $store->getKey())
            ->where('status', FiscalConfigurationStatus::Active)
            ->exists();
        $hasTaxCategory = $tenant->taxCategories()->exists();
        $sunatConfigured = filled(config('sunat.credentials.username')) && filled(config('sunat.certificate.path'));

        return [
            'company' => $this->state($tenant->status === TenantStatus::Active && filled($tenant->name), 'Company name and status'),
            'store' => $this->state($hasActiveStore, 'At least one active store'),
            'localization' => $this->state($store !== null && filled($store->currency) && filled($store->timezone) && filled($store->locale), 'Current store currency, timezone and locale'),
            'inventory' => $this->state($hasDefaultInventory, 'Default inventory location'),
            'pricing' => $this->state($hasDefaultPriceList, 'Default price list'),
            'payments' => $this->state($store?->paymentSettings()->exists() ?? false, 'Current store payment settings'),
            'fiscal' => $this->state($hasIssuer && $hasSeries && $hasTaxCategory, 'Issuer, active series and tax category'),
            'sunat' => [
                'status' => $sunatConfigured ? (config('sunat.production_enabled') ? 'Configured' : 'Beta configured') : 'Not configured',
                'detail' => 'Configuration presence only; credentials are never displayed',
            ],
        ];
    }

    /** @return array{status: string, detail: string} */
    private function state(bool $configured, string $detail): array
    {
        return ['status' => $configured ? 'Configured' : 'Needs attention', 'detail' => $detail];
    }
}
