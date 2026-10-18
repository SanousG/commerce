<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Fulfillment\Application\CreateShipment;
use App\Modules\Fulfillment\Application\MarkShipmentReady;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use Inertia\Testing\AssertableInertia;

class FulfillmentHttpAndIsolationTest extends FulfillmentFeatureTestCase
{
    public function test_owner_and_admin_can_read_and_mutate_fulfillments_and_shipments(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user, 2);
            $order->update(['delivery_type' => 'SHIPPING']);
            $this->makeCod($tenant, $store, $order);
            $reservation = $this->reservationFor($order);
            $this->useContext($user, $tenant, $store);

            $this->get(route('admin.fulfillments.index'))->assertOk();
            $this->post(route('admin.orders.fulfillments.store', $order), [
                'inventory_location_id' => $reservation->inventory_location_id,
                'items' => [['inventory_reservation_id' => $reservation->getKey(), 'quantity' => 1]],
            ])->assertRedirect();
            $fulfillment = $order->fulfillments()->sole();
            $this->get(route('admin.fulfillments.show', $fulfillment))->assertOk();
            $this->post(route('admin.fulfillments.start', $fulfillment))->assertRedirect();
            $this->post(route('admin.fulfillments.complete', $fulfillment))->assertRedirect();
            $this->post(route('admin.fulfillments.shipment.store', $fulfillment), [
                'carrier_name' => 'HTTP Carrier', 'tracking_url' => 'https://example.test/track',
            ])->assertRedirect();
            $shipment = $fulfillment->shipment()->sole();
            $this->post(route('admin.shipments.ready', $shipment))->assertRedirect();
            $this->post(route('admin.shipments.ship', $shipment))->assertRedirect();
            $this->post(route('admin.shipments.deliver', $shipment))->assertRedirect();

            $open = $this->fulfillment($tenant, $order, 1, $reservation, actor: $user);
            $this->post(route('admin.fulfillments.shipment.store', $open), [
                'carrier_name' => 'Cancellation Carrier',
            ])->assertRedirect();
            $openShipment = $open->shipment()->sole();
            $this->post(route('admin.shipments.cancel', $openShipment), ['reason' => 'HTTP cancellation.'])->assertRedirect();
            $this->post(route('admin.fulfillments.cancel', $open), ['reason' => 'HTTP cancellation.'])->assertRedirect();
        }
    }

    public function test_member_is_view_only_and_order_show_exposes_safe_fulfillment_props(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $member);
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 1);
        $shipment = app(CreateShipment::class)->handle($tenant, $fulfillment);
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.fulfillments.index'))->assertOk();
        $this->get(route('admin.fulfillments.show', $fulfillment))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('capabilities.manage', false)
            ->where('capabilities.complete', false)
            ->where('capabilities.shipments_manage', false));
        $this->get(route('admin.orders.show', $order))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fulfillmentData.can_manage', false)
            ->where('fulfillmentData.can_complete', false)
            ->where('fulfillmentData.can_manage_shipments', false)
            ->has('fulfillmentData.available_locations', 1)
            ->where('fulfillmentData.payment_eligibility.eligible', true));

        $reservation = $this->reservationFor($order);
        $this->post(route('admin.orders.fulfillments.store', $order), [
            'inventory_location_id' => $reservation->inventory_location_id,
            'items' => [['inventory_reservation_id' => $reservation->getKey(), 'quantity' => 1]],
        ])->assertForbidden();
        $this->post(route('admin.fulfillments.start', $fulfillment))->assertForbidden();
        $this->post(route('admin.fulfillments.complete', $fulfillment))->assertForbidden();
        $this->post(route('admin.fulfillments.cancel', $fulfillment), ['reason' => 'Denied.'])->assertForbidden();
        $this->post(route('admin.fulfillments.shipment.store', $fulfillment))->assertForbidden();
        $this->post(route('admin.shipments.ready', $shipment))->assertForbidden();
        $this->post(route('admin.shipments.ship', $shipment))->assertForbidden();
        $this->post(route('admin.shipments.deliver', $shipment))->assertForbidden();
        $this->post(route('admin.shipments.cancel', $shipment), ['reason' => 'Denied.'])->assertForbidden();
    }

    public function test_shipment_read_permission_protects_mixed_fulfillment_reads_and_nested_order_props(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $member);
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 1);
        $shipment = app(CreateShipment::class)->handle($tenant, $fulfillment, trackingNumber: 'PRIVATE-TRACKING');
        app(MarkShipmentReady::class)->handle($tenant, $shipment, $member);
        $notification = AdminNotification::query()
            ->where('type', NotificationType::ShipmentReady->value)
            ->sole();
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.fulfillments.index'))->assertOk();
        $this->get(route('admin.fulfillments.show', $fulfillment))->assertOk();

        Role::query()->system(SystemRole::Member)->sole()->permissions()->detach(
            Permission::query()->where('key', PermissionKey::ShipmentsView->value)->valueOrFail('id'),
        );

        $this->get(route('admin.fulfillments.index'))->assertForbidden();
        $this->get(route('admin.fulfillments.show', $fulfillment))->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('relatedLinks.fulfillments', null)
            ->missing('fulfillmentData.fulfillments.0.shipment_status'));
        $this->get(route('admin.notifications.index', ['type' => NotificationType::ShipmentReady->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 0));
        $this->post(route('admin.notifications.read', $notification))->assertNotFound();
    }

    public function test_fulfillment_and_shipment_routes_hide_other_store_and_tenant_records(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$orderB] = $this->readyOrder($tenant, $storeB);
        $orderB->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $storeB, $orderB);
        $fulfillmentB = $this->fulfillment($tenant, $orderB, 1);
        $shipmentB = app(CreateShipment::class)->handle($tenant, $fulfillmentB);

        $otherTenant = $this->createTenantFor(User::factory()->create());
        [$otherOrder] = $this->readyOrder($otherTenant, $otherStore = $this->createStore($otherTenant));
        $otherOrder->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($otherTenant, $otherStore, $otherOrder);
        $otherFulfillment = $this->fulfillment($otherTenant, $otherOrder, 1);
        $otherShipment = app(CreateShipment::class)->handle($otherTenant, $otherFulfillment);
        $this->useContext($user, $tenant, $storeA);

        foreach ([[$fulfillmentB, $shipmentB], [$otherFulfillment, $otherShipment]] as [$fulfillment, $shipment]) {
            $this->get(route('admin.fulfillments.show', $fulfillment))->assertNotFound();
            $this->post(route('admin.fulfillments.start', $fulfillment))->assertNotFound();
            $this->post(route('admin.fulfillments.complete', $fulfillment))->assertNotFound();
            $this->post(route('admin.fulfillments.cancel', $fulfillment), ['reason' => 'Isolation attempt.'])->assertNotFound();
            $this->post(route('admin.fulfillments.shipment.store', $fulfillment))->assertNotFound();
            $this->post(route('admin.shipments.ready', $shipment))->assertNotFound();
            $this->post(route('admin.shipments.ship', $shipment))->assertNotFound();
            $this->post(route('admin.shipments.deliver', $shipment))->assertNotFound();
            $this->post(route('admin.shipments.cancel', $shipment), ['reason' => 'Isolation attempt.'])->assertNotFound();
        }
    }
}
