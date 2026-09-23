<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Application\TenantInventoryAvailability;
use App\Modules\Listings\Application\ListingEligibility;
use App\Modules\Listings\Application\ListingPriceResolver;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddCartItem
{
    public function __construct(
        private readonly CartState $state,
        private readonly ListingEligibility $eligibility,
        private readonly ListingPriceResolver $prices,
        private readonly EffectivePriceCalculator $effectivePrices,
        private readonly TenantInventoryAvailability $availability,
    ) {}

    public function handle(Cart $cart, StoreListing $listing, ProductVariant $variant, int $quantity): CartItem
    {
        $this->state->ensureEditable($cart);

        return DB::transaction(function () use ($cart, $listing, $variant, $quantity): CartItem {
            $locked = Cart::query()->where('tenant_id', $cart->tenant_id)->where('store_id', $cart->store_id)
                ->lockForUpdate()->findOrFail($cart->getKey());
            $this->state->ensureEditable($locked);
            $this->validateQuantity($quantity);
            $this->ensureOwnership($locked, $listing, $variant);

            $result = $this->eligibility->evaluate($listing, $variant);
            if (! $result->eligible) {
                throw ValidationException::withMessages(['product_variant_id' => $result->reasons]);
            }
            $priceList = $this->prices->priceListFor($listing);
            $price = $priceList === null ? null : $this->effectivePrices->calculate($locked->tenant()->firstOrFail(), $variant, $priceList);
            if ($price === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'The variant has no resolvable price.']);
            }
            if ($locked->currency !== null && $locked->currency !== $priceList->currency) {
                throw ValidationException::withMessages(['product_variant_id' => 'All cart items must use the same currency.']);
            }

            $existing = $locked->items()->where('product_variant_id', $variant->getKey())->first();
            $newQuantity = ($existing?->quantity ?? 0) + $quantity;
            $this->validateQuantity($newQuantity);
            if ($this->availability->forVariant($locked->tenant()->firstOrFail(), $variant) < $newQuantity) {
                throw ValidationException::withMessages(['quantity' => 'The requested quantity is not currently available.']);
            }

            $item = $existing ?? new CartItem([
                'tenant_id' => $locked->tenant_id,
                'store_id' => $locked->store_id,
                'store_listing_id' => $listing->getKey(),
                'product_id' => $listing->product_id,
                'product_variant_id' => $variant->getKey(),
            ]);
            $item->quantity = $newQuantity;
            $locked->items()->save($item);
            if ($locked->currency === null) {
                $locked->currency = $priceList->currency;
                $locked->save();
            }
            $this->state->extend($locked);

            return $item->refresh();
        });
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > $this->state->maxQuantity()) {
            throw ValidationException::withMessages(['quantity' => "Quantity must be between 1 and {$this->state->maxQuantity()}."]);
        }
    }

    private function ensureOwnership(Cart $cart, StoreListing $listing, ProductVariant $variant): void
    {
        if ($listing->tenant_id !== $cart->tenant_id || $listing->store_id !== $cart->store_id
            || $variant->tenant_id !== $cart->tenant_id || $variant->product_id !== $listing->product_id) {
            throw (new ModelNotFoundException)->setModel(StoreListing::class, [$listing->getKey()]);
        }
    }
}
