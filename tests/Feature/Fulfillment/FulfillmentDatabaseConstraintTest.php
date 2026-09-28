<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Fulfillment\Application\CreateShipment;
use App\Modules\Fulfillment\Domain\Models\FulfillmentStatusHistory;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FulfillmentDatabaseConstraintTest extends FulfillmentFeatureTestCase
{
    public function test_fulfillment_permission_matrix_gives_members_view_only(): void
    {
        $all = [
            PermissionKey::FulfillmentsView->value,
            PermissionKey::FulfillmentsManage->value,
            PermissionKey::FulfillmentsComplete->value,
            PermissionKey::ShipmentsView->value,
            PermissionKey::ShipmentsManage->value,
        ];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($all, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'fulfillment')->pluck('key')->all());
        }
        $this->assertEqualsCanonicalizing(
            [PermissionKey::FulfillmentsView->value, PermissionKey::ShipmentsView->value],
            Role::query()->system(SystemRole::Member)->sole()->permissions()
                ->where('module', 'fulfillment')->pluck('key')->all(),
        );
    }

    public function test_fulfillment_item_composite_foreign_keys_reject_cross_store_identity(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $item = $this->fulfillment($tenant, $order, 1)->items->sole();

        $this->expectException(QueryException::class);
        DB::table('fulfillment_items')->where('id', $item->getKey())->update(['store_id' => (string) Str::uuid()]);
    }

    public function test_fulfillment_quantity_must_be_positive(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $item = $this->fulfillment($tenant, $order, 1)->items->sole();

        $this->expectException(QueryException::class);
        DB::table('fulfillment_items')->where('id', $item->getKey())->update(['quantity' => 0]);
    }

    public function test_database_rejects_invalid_fulfillment_status(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 1);

        $this->expectException(QueryException::class);
        DB::table('fulfillments')->where('id', $fulfillment->getKey())->update(['status' => 'INVALID']);
    }

    public function test_database_rejects_invalid_shipment_status(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $shipment = app(CreateShipment::class)->handle($tenant, $this->fulfillment($tenant, $order, 1));

        $this->expectException(QueryException::class);
        DB::table('shipments')->where('id', $shipment->getKey())->update(['status' => 'INVALID']);
    }

    public function test_database_allows_only_one_shipment_per_fulfillment(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $shipment = app(CreateShipment::class)->handle($tenant, $this->fulfillment($tenant, $order, 1));

        $this->expectException(QueryException::class);
        Shipment::query()->create([
            'tenant_id' => $shipment->tenant_id, 'store_id' => $shipment->store_id,
            'order_id' => $shipment->order_id, 'fulfillment_id' => $shipment->fulfillment_id,
            'status' => 'PENDING',
        ]);
    }

    public function test_fulfillment_history_is_database_append_only(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $history = $this->fulfillment($tenant, $order, 1)->history->sole();

        $this->expectException(QueryException::class);
        DB::table('fulfillment_status_histories')->where('id', $history->getKey())->update(['reason' => 'rewrite']);
    }

    public function test_shipment_history_is_database_append_only(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $order->update(['delivery_type' => 'SHIPPING']);
        $this->makeCod($tenant, $store, $order);
        $shipment = app(CreateShipment::class)->handle($tenant, $this->fulfillment($tenant, $order, 1));

        $this->expectException(QueryException::class);
        DB::table('shipment_status_histories')->where('id', $shipment->history->sole()->getKey())->delete();
    }

    public function test_history_transition_constraints_reject_equal_states(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 1);

        $this->expectException(QueryException::class);
        FulfillmentStatusHistory::query()->create([
            'tenant_id' => $tenant->getKey(), 'fulfillment_id' => $fulfillment->getKey(),
            'from_status' => 'PENDING', 'to_status' => 'PENDING',
        ]);
    }
}
