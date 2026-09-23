<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Cart\Application\CartState;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Enums\CheckoutDeliveryType;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartCheckout
{
    public function __construct(private readonly CartState $cartState) {}

    public function handle(Tenant $tenant, Store $store, Cart $cart, string $idempotencyKey): CheckoutSession
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }

        return DB::transaction(function () use ($tenant, $store, $cart, $idempotencyKey): CheckoutSession {
            $scoped = Cart::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($cart->getKey());
            $sameRequest = CheckoutSession::query()->where('cart_id', $scoped->getKey())
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($sameRequest !== null) {
                return $sameRequest;
            }

            $this->cartState->ensureEditable($scoped);
            if (! $scoped->items()->exists()) {
                throw ValidationException::withMessages(['cart' => 'Checkout requires at least one cart item.']);
            }
            if (CheckoutSession::query()->where('cart_id', $scoped->getKey())
                ->whereIn('status', [CheckoutStatus::Pending, CheckoutStatus::Ready])->exists()) {
                throw ValidationException::withMessages(['cart' => 'This cart already has an operational checkout.']);
            }

            $customer = $scoped->customer()->first();

            return CheckoutSession::create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'cart_id' => $scoped->getKey(),
                'customer_id' => $customer?->getKey(), 'token' => hash('sha256', Str::random(64)),
                'idempotency_key' => $idempotencyKey, 'status' => CheckoutStatus::Pending,
                'delivery_type' => CheckoutDeliveryType::Shipping, 'currency' => $scoped->currency,
                'customer_name' => $customer?->displayName(), 'email' => $customer?->email, 'phone' => $customer?->phone,
                'subtotal_before_discounts' => '0.0000', 'discount_total' => '0.0000', 'subtotal' => '0.0000',
                'shipping_total' => '0.0000', 'tax_total' => '0.0000', 'grand_total' => '0.0000',
            ]);
        });
    }
}
