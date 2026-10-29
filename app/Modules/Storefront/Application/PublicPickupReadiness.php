<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final class PublicPickupReadiness
{
    /** @return array<string, mixed> */
    public function evaluate(Tenant $tenant, Store $store, bool $lockForUpdate = false): array
    {
        $settingsQuery = StoreSettings::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey());
        $paymentsQuery = StorePaymentSettings::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey());
        if ($lockForUpdate) {
            $settingsQuery->lockForUpdate();
            $paymentsQuery->lockForUpdate();
        }
        $settings = $settingsQuery->first();
        $payments = $paymentsQuery->first();
        $locationQuery = $settings?->pickup_inventory_location_id === null ? null : InventoryLocation::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', InventoryLocationStatus::Active);
        if ($lockForUpdate) {
            $locationQuery?->lockForUpdate();
        }
        $location = $locationQuery?->find($settings->pickup_inventory_location_id);

        $addressComplete = $settings !== null && collect([
            $settings->address_line_1,
            $settings->district,
            $settings->province,
            $settings->department,
            $settings->country_code,
        ])->every(fn (mixed $value): bool => is_string($value) && filled(trim($value)));
        $depositNone = $payments?->cod_deposit_type === CodDepositType::None
            && BigDecimal::of((string) $payments->cod_deposit_value)->isZero();

        $pickupRestrictions = [];
        if (! $settings?->pickup_enabled) {
            $pickupRestrictions[] = 'PICKUP_DISABLED';
        }
        if (! $addressComplete) {
            $pickupRestrictions[] = 'PICKUP_ADDRESS_INCOMPLETE';
        }
        if ($location === null) {
            $pickupRestrictions[] = 'PICKUP_LOCATION_UNAVAILABLE';
        }

        $paymentRestrictions = [];
        if (! $payments?->cash_enabled) {
            $paymentRestrictions[] = 'CASH_DISABLED';
        }
        if (! $payments?->cod_enabled) {
            $paymentRestrictions[] = 'COD_DISABLED';
        }
        if (! $depositNone) {
            $paymentRestrictions[] = 'COD_DEPOSIT_REQUIRED';
        }

        return [
            'available' => $pickupRestrictions === [] && $paymentRestrictions === [],
            'pickupAvailable' => $pickupRestrictions === [],
            'paymentAvailable' => $paymentRestrictions === [],
            'restrictions' => [...$pickupRestrictions, ...$paymentRestrictions],
            'pickup' => $addressComplete && $location !== null ? [
                'name' => $settings->display_name ?: $store->name,
                'inventoryLocationId' => (string) $location->getKey(),
                'addressLine1' => $settings->address_line_1,
                'addressLine2' => $settings->address_line_2,
                'district' => $settings->district,
                'province' => $settings->province,
                'department' => $settings->department,
                'countryCode' => $settings->country_code,
                'postalCode' => $settings->postal_code,
                'instructions' => $settings->pickup_instructions,
            ] : null,
            'payment' => $paymentRestrictions === [] ? [
                'expectedMethod' => PaymentMethod::Cash->value,
                'terms' => PaymentTerms::Cod->value,
                'depositType' => CodDepositType::None->value,
                'depositValue' => '0.0000',
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function requireAvailable(Tenant $tenant, Store $store, bool $lockForUpdate = false): array
    {
        $readiness = $this->evaluate($tenant, $store, $lockForUpdate);
        if (! $readiness['available']) {
            throw ValidationException::withMessages([
                'pickup' => __('Store pickup and cash payment on pickup are not currently available.'),
            ]);
        }

        return $readiness;
    }
}
