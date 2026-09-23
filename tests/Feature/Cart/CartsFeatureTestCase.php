<?php

namespace Tests\Feature\Cart;

use App\Modules\Cart\Application\CreateCart;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\Listings\ListingsFeatureTestCase;

abstract class CartsFeatureTestCase extends ListingsFeatureTestCase
{
    /** @return array{Cart, StoreListing, ProductVariant, InventoryLevel} */
    protected function sellableCart(Store $store, string $price = '100.0000', int $stock = 20, bool $additionalVariant = false): array
    {
        $tenant = $store->tenant()->firstOrFail();
        $product = $this->createProduct($tenant);
        $variant = $additionalVariant ? $this->addVariant($tenant, $product) : $product->variants()->sole();
        $listing = $this->createListing($store, $product);
        $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, $price);
        $level = $this->stock($tenant, $variant, $stock);

        return [app(CreateCart::class)->handle($store), $listing->fresh(), $variant, $level];
    }

    protected function stock(Tenant $tenant, ProductVariant $variant, int $quantity): InventoryLevel
    {
        $location = $tenant->inventoryLocations()->first() ?? InventoryLocation::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Main warehouse',
            'code' => 'MAIN-'.Str::upper(Str::random(6)),
            'status' => InventoryLocationStatus::Active,
            'is_default' => true,
        ]);

        return InventoryLevel::query()->updateOrCreate([
            'tenant_id' => $tenant->getKey(),
            'inventory_location_id' => $location->getKey(),
            'product_variant_id' => $variant->getKey(),
        ], ['on_hand' => $quantity, 'reserved' => 0]);
    }

    protected function createCustomer(Tenant $tenant, string $email = 'cart-customer@example.test'): Customer
    {
        return Customer::create([
            'tenant_id' => $tenant->getKey(),
            'first_name' => 'Cart',
            'last_name' => 'Customer',
            'email' => $email,
            'phone' => null,
            'status' => CustomerStatus::Active,
            'notes' => null,
        ]);
    }
}
