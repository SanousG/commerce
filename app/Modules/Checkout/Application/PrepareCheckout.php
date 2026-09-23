<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Cart\Application\CartState;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Enums\CheckoutDeliveryType;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrepareCheckout
{
    public function __construct(
        private readonly CartState $cartState,
        private readonly CartCalculator $calculator,
        private readonly CheckoutInventoryAllocator $inventory,
    ) {}

    public function handle(Tenant $tenant, CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($tenant, $checkout): CheckoutSession {
            $scoped = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($checkout->getKey());
            if ($scoped->status !== CheckoutStatus::Pending) {
                throw ValidationException::withMessages(['checkout' => 'Only a pending checkout can be prepared.']);
            }
            $this->validateContactAndDelivery($scoped);

            $cart = Cart::query()->where('tenant_id', $tenant->getKey())->where('store_id', $scoped->store_id)
                ->lockForUpdate()->findOrFail($scoped->cart_id);
            $this->cartState->ensureEditable($cart);
            $cart->load(['tenant', 'items' => fn ($query) => $query->orderBy('product_variant_id')]);
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Checkout requires at least one cart item.']);
            }
            $summary = $this->calculator->calculate($cart);
            if (! $summary->valid || $summary->currency === null) {
                throw ValidationException::withMessages(['cart' => 'The cart has issues and cannot be prepared.']);
            }

            $scoped->lines()->delete();
            foreach ($summary->items as $item) {
                $promotion = $item['applied_promotions'][0] ?? null;
                $scoped->lines()->create([
                    'tenant_id' => $scoped->tenant_id, 'store_id' => $scoped->store_id,
                    'store_listing_id' => $item['listing_id'], 'product_id' => $item['product_id'],
                    'product_variant_id' => $item['variant_id'], 'price_list_id' => $item['price_list_id'],
                    'promotion_id' => $promotion['id'] ?? null, 'promotion_name' => $promotion['name'] ?? null,
                    'product_name' => $item['product'], 'variant_name' => $item['variant'], 'sku' => $item['sku'],
                    'quantity' => $item['quantity'], 'base_unit_price' => $item['base_unit_price'],
                    'discount_unit_amount' => BigDecimal::of($item['base_unit_price'])->minus($item['final_unit_price'])->toScale(4),
                    'final_unit_price' => $item['final_unit_price'], 'line_base_total' => $item['line_base_total'],
                    'line_discount_total' => $item['line_discount'], 'line_total' => $item['line_total'],
                    'currency' => $summary->currency,
                ]);
            }

            $expiresAt = now()->addMinutes(max(1, (int) config('commerce.checkout_ttl_minutes', 15)));
            $this->inventory->reserve($tenant, $scoped->lines()->get(), $expiresAt);
            $scoped->update([
                'status' => CheckoutStatus::Ready, 'currency' => $summary->currency,
                'subtotal_before_discounts' => $summary->subtotalBeforeDiscounts,
                'discount_total' => $summary->discountTotal, 'subtotal' => $summary->subtotal,
                'shipping_total' => '0.0000', 'tax_total' => '0.0000', 'grand_total' => $summary->subtotal,
                'expires_at' => $expiresAt,
            ]);

            return $scoped->fresh(['lines.reservations.location']);
        });
    }

    private function validateContactAndDelivery(CheckoutSession $checkout): void
    {
        if (blank($checkout->customer_name) || (blank($checkout->email) && blank($checkout->phone))) {
            throw ValidationException::withMessages(['contact' => 'Name and at least an email or phone are required.']);
        }
        if ($checkout->delivery_type === CheckoutDeliveryType::Shipping
            && (blank($checkout->shipping_recipient_name) || blank($checkout->shipping_country_code)
                || blank($checkout->shipping_city) || blank($checkout->shipping_address_line_1))) {
            throw ValidationException::withMessages(['shipping' => 'Shipping recipient, country, city, and address are required.']);
        }
    }
}
