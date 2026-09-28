<?php

namespace Tests\Feature\Checkout;

use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tax\Application\SaveTaxCategory;
use App\Modules\Tax\Application\SaveTaxRate;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\Cart\CartsFeatureTestCase;

abstract class CheckoutFeatureTestCase extends CartsFeatureTestCase
{
    /** @return array{CheckoutSession, Cart, StoreListing, mixed, mixed} */
    protected function pendingCheckout(Store $store, int $quantity = 2, string $price = '100.0000', int $stock = 20): array
    {
        [$cart, $listing, $variant, $level] = $this->sellableCart($store, $price, $stock);
        $tenant = $store->tenant()->firstOrFail();
        $category = $tenant->taxCategories()->where('code', 'STANDARD')->first()
            ?? app(SaveTaxCategory::class)->create($tenant, [
                'code' => 'STANDARD', 'name' => 'Standard', 'status' => 'ACTIVE',
            ]);
        if (! $category->rates()->exists()) {
            app(SaveTaxRate::class)->create($tenant, [
                'tax_category_id' => $category->getKey(), 'name' => '18 percent', 'rate' => '18.000000',
                'valid_from' => null, 'valid_until' => null, 'status' => 'ACTIVE',
            ]);
        }
        $listing->product()->firstOrFail()->update(['tax_category_id' => $category->getKey()]);
        app(AddCartItem::class)->handle($cart, $listing, $variant, $quantity);
        $checkout = app(StartCheckout::class)->handle(
            $tenant, $store, $cart->fresh(), (string) Str::uuid(),
        );

        return [$checkout, $cart->fresh(), $listing, $variant, $level];
    }

    protected function makeReady(Tenant $tenant, CheckoutSession $checkout): CheckoutSession
    {
        app(UpdateCheckout::class)->handle($tenant, $checkout, [
            'customer_name' => 'Checkout Buyer', 'email' => 'buyer@example.test', 'phone' => null,
            'delivery_type' => 'PICKUP',
        ]);

        return app(PrepareCheckout::class)->handle($tenant, $checkout->fresh());
    }
}
