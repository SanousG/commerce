<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Application\CompleteFulfillment;
use App\Modules\Fulfillment\Application\CreateFulfillment;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Inventory\Application\AdjustInventory;
use App\Modules\Inventory\Application\CreateInventoryLocation;
use App\Modules\Inventory\Application\ReserveInventory;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Application\ConfigureOrderPaymentTerms;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\Payments\PaymentsFeatureTestCase;

abstract class FulfillmentFeatureTestCase extends PaymentsFeatureTestCase
{
    protected function reservationFor(Order $order): InventoryReservation
    {
        $lineIds = $order->items()->pluck('checkout_line_id');

        return InventoryReservation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('reference_type', 'CHECKOUT_LINE')
            ->whereIn('reference_id', $lineIds)
            ->sole();
    }

    protected function pay(Tenant $tenant, Store $store, Order $order, string $amount, ?User $actor = null): void
    {
        $payment = $this->manualPayment($tenant, $store, $order, $amount, $actor);
        app(CaptureManualPayment::class)->handle(
            $tenant,
            $store,
            $payment,
            $amount,
            (string) Str::uuid(),
            actor: $actor,
        );
    }

    protected function makeCod(
        Tenant $tenant,
        Store $store,
        Order $order,
        CodDepositType $type = CodDepositType::None,
        string $value = '0.0000',
    ): void {
        $settings = StorePaymentSettings::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey())
            ->first();
        if ($settings === null) {
            $this->enablePayments($tenant, $store, $type, $value);
        } else {
            $settings->update([
                'cod_enabled' => true,
                'cod_deposit_type' => $type,
                'cod_deposit_value' => $value,
            ]);
        }
        app(ConfigureOrderPaymentTerms::class)->handle($tenant, $store, $order, PaymentTerms::Cod);
    }

    protected function fulfillment(
        Tenant $tenant,
        Order $order,
        int $quantity,
        ?InventoryReservation $reservation = null,
        ?InventoryLocation $location = null,
        ?User $actor = null,
    ): Fulfillment {
        $reservation ??= $this->reservationFor($order);
        $location ??= $reservation->location()->firstOrFail();
        $item = $order->items()->where('checkout_line_id', $reservation->reference_id)->sole();

        return app(CreateFulfillment::class)->handle($tenant, $order, $location, [[
            'order_item_id' => $item->getKey(),
            'inventory_reservation_id' => $reservation->getKey(),
            'quantity' => $quantity,
        ]], $actor);
    }

    protected function complete(Tenant $tenant, Fulfillment $fulfillment, ?User $actor = null): Fulfillment
    {
        app(StartFulfillment::class)->handle($tenant, $fulfillment, $actor);

        return app(CompleteFulfillment::class)->handle($tenant, $fulfillment->fresh(), $actor);
    }

    /** @return array{InventoryReservation, InventoryReservation, InventoryLocation} */
    protected function splitReservationAcrossLocations(
        Tenant $tenant,
        Order $order,
        int $firstQuantity,
        int $secondQuantity,
        ?User $actor = null,
    ): array {
        $first = $this->reservationFor($order);
        $firstLevel = $first->location->levels()->where('product_variant_id', $first->product_variant_id)->sole();
        $first->update(['quantity' => $firstQuantity]);
        $firstLevel->update(['reserved' => $firstQuantity]);

        $secondLocation = app(CreateInventoryLocation::class)->handle($tenant, [
            'name' => fake()->unique()->city().' Warehouse',
            'code' => 'FUL-'.Str::upper(Str::random(8)),
            'status' => 'ACTIVE',
        ]);
        app(AdjustInventory::class)->handle(
            $tenant,
            $secondLocation,
            $first->variant,
            20,
            $actor ?? User::factory()->create(),
            'Fulfillment test stock',
        );
        $second = app(ReserveInventory::class)->handle(
            $tenant,
            $secondLocation,
            $first->variant,
            $secondQuantity,
            'CHECKOUT_LINE',
            $first->reference_id,
            now()->addMinutes(30),
        );

        return [$first->fresh(), $second, $secondLocation];
    }
}
