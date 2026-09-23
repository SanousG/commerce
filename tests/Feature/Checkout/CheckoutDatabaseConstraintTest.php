<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CheckoutDatabaseConstraintTest extends CheckoutFeatureTestCase
{
    public function test_checkout_permissions_have_expected_role_matrix(): void
    {
        $keys = [PermissionKey::CheckoutsView->value, PermissionKey::CheckoutsManage->value];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'checkouts')->pluck('key')->all());
        }
        $this->assertSame([PermissionKey::CheckoutsView->value], Role::query()->system(SystemRole::Member)
            ->sole()->permissions()->where('module', 'checkouts')->pluck('key')->all());
    }

    public function test_database_rejects_second_operational_checkout_for_cart(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout] = $this->pendingCheckout($this->createStore($tenant));
        $duplicate = $checkout->replicate(['id', 'token', 'idempotency_key']);
        $duplicate->id = (string) Str::uuid7();
        $duplicate->token = hash('sha256', Str::random(64));
        $duplicate->idempotency_key = (string) Str::uuid();

        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_database_rejects_invalid_checkout_money(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout] = $this->pendingCheckout($this->createStore($tenant));

        $this->expectException(QueryException::class);
        $checkout->update(['subtotal' => '-1.0000', 'grand_total' => '-1.0000']);
    }

    public function test_database_rejects_invalid_line_arithmetic(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout] = $this->pendingCheckout($this->createStore($tenant));
        $line = $this->makeReady($tenant, $checkout)->lines()->sole();

        $this->expectException(QueryException::class);
        $line->update(['line_total' => '0.0000']);
    }

    public function test_database_rejects_cross_store_cart_relationship(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$checkout] = $this->pendingCheckout($this->createStore($tenant));
        [, $foreignCart] = $this->pendingCheckout($this->createStore($tenant));

        $this->expectException(QueryException::class);
        CheckoutSession::query()->whereKey($checkout->getKey())->update(['cart_id' => $foreignCart->getKey()]);
    }
}
