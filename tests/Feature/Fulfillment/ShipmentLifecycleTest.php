<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Application\CreateShipment;
use App\Modules\Fulfillment\Application\MarkShipmentDelivered;
use App\Modules\Fulfillment\Application\MarkShipmentReady;
use App\Modules\Fulfillment\Application\MarkShipmentShipped;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use Illuminate\Validation\ValidationException;

class ShipmentLifecycleTest extends FulfillmentFeatureTestCase
{
    public function test_shipping_follows_pending_ready_shipped_delivered_without_inventory_or_payment_changes(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 1);
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order, CodDepositType::FixedAmount, '10.0000');
        $this->pay($tenant, $store, $order, '10.0000', $actor);
        $fulfillment = $this->complete($tenant, $this->fulfillment($tenant, $order, 1, actor: $actor), $actor);
        $inventory = [$level->fresh()->on_hand, $level->fresh()->reserved, InventoryMovement::query()->count()];

        $shipment = app(CreateShipment::class)->handle(
            $tenant, $fulfillment, 'Test Carrier', 'Express', 'TRACK-1', 'https://example.test/TRACK-1', $actor,
        );
        $shipment = app(MarkShipmentReady::class)->handle($tenant, $shipment, $actor);
        $this->assertDatabaseHas('admin_notifications', [
            'tenant_id' => $tenant->getKey(),
            'deduplication_key' => 'event:shipment-ready:'.$shipment->getKey(),
            'type' => NotificationType::ShipmentReady->value,
        ]);
        $shipment = app(MarkShipmentShipped::class)->handle($tenant, $shipment, $actor);
        $shipment = app(MarkShipmentDelivered::class)->handle($tenant, $shipment, $actor);

        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->ready_at);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(
            [ShipmentStatus::Pending, ShipmentStatus::Ready, ShipmentStatus::Shipped, ShipmentStatus::Delivered],
            $shipment->history->pluck('to_status')->all(),
        );
        $this->assertSame([$actor->getKey(), $actor->getKey(), $actor->getKey(), $actor->getKey()], $shipment->history->pluck('actor_user_id')->all());
        $this->assertSame($inventory, [$level->fresh()->on_hand, $level->fresh()->reserved, InventoryMovement::query()->count()]);
        $this->assertSame(OrderPaymentStatus::PartiallyPaid, $order->fresh()->payment_status);
    }

    public function test_shipment_cannot_ship_before_fulfillment_is_complete(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 1);
        $shipment = app(CreateShipment::class)->handle($tenant, $fulfillment);
        app(MarkShipmentReady::class)->handle($tenant, $shipment);

        $this->expectException(ValidationException::class);
        app(MarkShipmentShipped::class)->handle($tenant, $shipment->fresh());
    }

    public function test_pickup_rejects_shipment_and_one_fulfillment_has_only_one_shipment(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$pickup] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $pickup);
        $pickupFulfillment = $this->fulfillment($tenant, $pickup, 1);
        try {
            app(CreateShipment::class)->handle($tenant, $pickupFulfillment);
            $this->fail('Pickup order accepted a shipment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shipment', $exception->errors());
        }

        [$shipping] = $this->readyOrder($tenant, $store);
        $shipping->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $shipping);
        $fulfillment = $this->fulfillment($tenant, $shipping, 1);
        $first = app(CreateShipment::class)->handle($tenant, $fulfillment, 'Carrier A');
        $duplicate = app(CreateShipment::class)->handle($tenant, $fulfillment->fresh(), 'Carrier B');
        $this->assertSame($first->getKey(), $duplicate->getKey());
        $this->assertSame('Carrier A', $duplicate->carrier_name);
        $this->assertSame(1, $fulfillment->shipment()->count());
    }
}
