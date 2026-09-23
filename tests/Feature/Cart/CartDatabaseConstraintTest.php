<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Cart\Application\AddCartItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartDatabaseConstraintTest extends CartsFeatureTestCase
{
    public function test_cart_permissions_have_expected_role_matrix(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $keys = DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('roles.key', $role->value)->whereIn('permissions.key', ['carts.view', 'carts.manage'])->pluck('permissions.key');
            $this->assertCount(2, $keys);
        }
        $memberKeys = DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.key', SystemRole::Member->value)->whereIn('permissions.key', ['carts.view', 'carts.manage'])->pluck('permissions.key');
        $this->assertSame(['carts.view'], $memberKeys->all());
    }

    public function test_database_rejects_invalid_quantity_and_mismatched_cart_listing_integrity(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$cart, $listingA, $variantA] = $this->sellableCart($storeA);
        $item = app(AddCartItem::class)->handle($cart, $listingA, $variantA, 1);
        [, $listingB, $variantB] = $this->sellableCart($storeB);

        foreach ([
            [...$item->getAttributes(), 'id' => (string) Str::uuid7(), 'quantity' => 0],
            [...$item->getAttributes(), 'id' => (string) Str::uuid7(), 'store_listing_id' => $listingB->getKey(), 'product_id' => $listingB->product_id, 'product_variant_id' => $variantB->getKey()],
        ] as $row) {
            unset($row['updated_at']);
            $row['created_at'] = now();
            $row['updated_at'] = now();
            try {
                DB::transaction(fn () => DB::table('cart_items')->insert($row));
                $this->fail('Expected cart item integrity to be rejected.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }
}
