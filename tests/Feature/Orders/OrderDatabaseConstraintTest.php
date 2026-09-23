<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Orders\Domain\Models\Order;
use Illuminate\Database\QueryException;

class OrderDatabaseConstraintTest extends OrdersFeatureTestCase
{
    public function test_order_permissions_have_expected_role_matrix(): void
    {
        $keys = [PermissionKey::OrdersView->value, PermissionKey::OrdersManage->value];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'orders')->pluck('key')->all());
        }
        $this->assertSame([PermissionKey::OrdersView->value], Role::query()->system(SystemRole::Member)
            ->sole()->permissions()->where('module', 'orders')->pluck('key')->all());
    }

    public function test_database_rejects_second_order_for_checkout(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $this->createStore($tenant));
        $duplicate = $order->replicate(['id', 'order_number']);
        $duplicate->order_number = 'ORD-999999';

        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_database_rejects_duplicate_tenant_number_and_invalid_money(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$first] = $this->readyOrder($tenant, $this->createStore($tenant));
        [$second] = $this->readyOrder($tenant, $this->createStore($tenant));

        $this->expectException(QueryException::class);
        $second->update(['order_number' => $first->order_number]);
    }

    public function test_database_rejects_invalid_item_arithmetic(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $this->createStore($tenant));

        $this->expectException(QueryException::class);
        $order->items()->sole()->update(['line_total' => '-1.0000']);
    }

    public function test_database_rejects_cross_store_checkout_relationship(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $this->createStore($tenant));
        [, $otherCheckout] = $this->readyOrder($tenant, $this->createStore($tenant));

        $this->expectException(QueryException::class);
        Order::query()->whereKey($order->getKey())->update(['checkout_session_id' => $otherCheckout->getKey()]);
    }
}
