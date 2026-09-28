<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Application\AddPurchaseOrderItem;
use App\Modules\Procurement\Application\AddPurchaseReceiptItem;
use App\Modules\Procurement\Application\CreatePurchaseOrder;
use App\Modules\Procurement\Application\CreatePurchaseReceipt;
use App\Modules\Procurement\Application\CreateSupplier;
use App\Modules\Procurement\Application\CreateSupplierProduct;
use App\Modules\Procurement\Application\MarkPurchaseOrderOrdered;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\Inventory\InventoryFeatureTestCase;

abstract class ProcurementFeatureTestCase extends InventoryFeatureTestCase
{
    protected function supplier(Tenant $tenant, string $code = ''): Supplier
    {
        return app(CreateSupplier::class)->handle(
            $tenant,
            $code !== '' ? $code : 'SUP-'.Str::upper(Str::random(8)),
            fake()->unique()->company(),
        );
    }

    protected function supplierProduct(
        Tenant $tenant,
        Supplier $supplier,
        ProductVariant $variant,
        array $attributes = [],
    ): SupplierProduct {
        return app(CreateSupplierProduct::class)->handle(
            $tenant,
            $supplier,
            $variant,
            $attributes['supplier_sku'] ?? 'VND-'.Str::upper(Str::random(8)),
            $attributes['unit_cost'] ?? '12.3456',
            $attributes['currency'] ?? 'USD',
            $attributes['minimum_order_quantity'] ?? 1,
            $attributes['order_multiple'] ?? null,
            $attributes['lead_time_days'] ?? null,
            $attributes['is_preferred'] ?? false,
        );
    }

    protected function draftOrder(
        Tenant $tenant,
        Supplier $supplier,
        SupplierProduct $product,
        int $quantity = 5,
        ?User $actor = null,
    ): PurchaseOrder {
        $order = app(CreatePurchaseOrder::class)->handle($tenant, $supplier, $product->currency, actor: $actor);
        app(AddPurchaseOrderItem::class)->handle($tenant, $order, $product, $quantity);

        return $order->fresh('items');
    }

    protected function orderedOrder(
        Tenant $tenant,
        Supplier $supplier,
        SupplierProduct $product,
        User $actor,
        int $quantity = 5,
    ): PurchaseOrder {
        $order = $this->draftOrder($tenant, $supplier, $product, $quantity, $actor);
        // Approval has its own regression test; seed the valid state so downstream behavior remains covered.
        $order->update([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by_user_id' => $actor->getKey(),
        ]);

        return app(MarkPurchaseOrderOrdered::class)->handle($tenant, $order->fresh());
    }

    protected function receipt(
        Tenant $tenant,
        PurchaseOrder $order,
        InventoryLocation $location,
        User $actor,
        int $quantity,
        string $reference = '',
    ): PurchaseReceipt {
        $receipt = app(CreatePurchaseReceipt::class)->handle(
            $tenant,
            $order,
            $location,
            now()->toISOString(),
            $actor,
            $reference !== '' ? $reference : 'RCV-'.Str::upper(Str::random(8)),
        );
        app(AddPurchaseReceiptItem::class)->handle($tenant, $receipt, $order->items()->sole(), $quantity);

        return $receipt->fresh('items');
    }

    protected function createStore(Tenant $tenant, string $name = ''): Store
    {
        $name = $name !== '' ? $name : fake()->unique()->company();

        return Store::create([
            'tenant_id' => $tenant->getKey(), 'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => StoreStatus::Active, 'currency' => 'USD',
            'timezone' => 'UTC', 'locale' => 'en_US',
        ]);
    }

    protected function useTenant(User $user, Tenant $tenant, ?Store $store = null): void
    {
        $session = [TenantContext::SESSION_KEY => $tenant->getKey()];
        if ($store !== null) {
            $session[StoreContext::SESSION_KEY] = $store->getKey();
        }

        $this->actingAs($user)->withSession($session);
    }
}
