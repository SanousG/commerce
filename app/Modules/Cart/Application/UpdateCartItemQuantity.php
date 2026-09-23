<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;
use App\Modules\Inventory\Application\TenantInventoryAvailability;
use App\Modules\Listings\Application\ListingEligibility;
use App\Modules\Listings\Application\ListingPriceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
    public function __construct(
        private readonly CartState $state,
        private readonly ListingEligibility $eligibility,
        private readonly ListingPriceResolver $prices,
        private readonly TenantInventoryAvailability $availability,
    ) {}

    public function handle(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        $this->state->ensureEditable($cart);

        return DB::transaction(function () use ($cart, $item, $quantity): CartItem {
            $locked = Cart::query()->where('tenant_id', $cart->tenant_id)->where('store_id', $cart->store_id)
                ->lockForUpdate()->findOrFail($cart->getKey());
            $this->state->ensureEditable($locked);
            if ($quantity < 1 || $quantity > $this->state->maxQuantity()) {
                throw ValidationException::withMessages(['quantity' => "Quantity must be between 1 and {$this->state->maxQuantity()}."]);
            }

            $scopedItem = $locked->items()->with(['listing', 'variant'])->findOrFail($item->getKey());
            $eligibility = $this->eligibility->evaluate($scopedItem->listing, $scopedItem->variant);
            if (! $eligibility->eligible) {
                throw ValidationException::withMessages(['quantity' => $eligibility->reasons]);
            }
            $priceList = $this->prices->priceListFor($scopedItem->listing);
            if ($priceList === null || $priceList->currency !== $locked->currency) {
                throw ValidationException::withMessages(['quantity' => 'The item price or currency is no longer valid.']);
            }
            if ($quantity > $scopedItem->quantity
                && $this->availability->forVariant($locked->tenant()->firstOrFail(), $scopedItem->variant) < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The requested quantity is not currently available.']);
            }

            $scopedItem->update(['quantity' => $quantity]);
            $this->state->extend($locked);

            return $scopedItem->refresh();
        });
    }
}
