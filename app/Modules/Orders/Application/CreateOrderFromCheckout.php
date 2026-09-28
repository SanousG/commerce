<?php

namespace App\Modules\Orders\Application;

use App\Models\User;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Application\CheckoutInventoryAllocator;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderFromCheckout
{
    public function __construct(private readonly GenerateOrderNumber $numbers) {}

    public function handle(Tenant $tenant, Store $store, CheckoutSession $checkout, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($tenant, $store, $checkout, $actor): Order {
            $locked = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($checkout->getKey());
            $existing = Order::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('checkout_session_id', $locked->getKey())->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($locked->status !== CheckoutStatus::Ready) {
                throw ValidationException::withMessages(['checkout' => 'Only a ready checkout can create an order.']);
            }
            if ($locked->tax_snapshot_version !== 1) {
                throw ValidationException::withMessages(['tax' => 'CHECKOUT_TAX_SNAPSHOT_MISSING']);
            }
            if ($locked->expires_at === null || $locked->expires_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['checkout' => 'This checkout has expired.']);
            }

            $cart = Cart::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($locked->cart_id);
            if ($cart->status !== CartStatus::Active) {
                throw ValidationException::withMessages(['cart' => 'The checkout cart is no longer active.']);
            }
            $lines = $locked->lines()->orderBy('id')->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['checkout' => 'A ready checkout must contain lines.']);
            }
            $this->validateTaxSnapshot($locked, $lines);
            $this->validateReservations($tenant, $lines);

            $order = Order::query()->create([
                ...$locked->only([
                    'tenant_id', 'store_id', 'cart_id', 'customer_id', 'currency', 'customer_name', 'email', 'phone',
                    'shipping_recipient_name', 'shipping_phone', 'shipping_country_code', 'shipping_region',
                    'shipping_city', 'shipping_district', 'shipping_address_line_1', 'shipping_address_line_2',
                    'shipping_postal_code', 'shipping_reference', 'billing_recipient_name', 'billing_phone',
                    'billing_country_code', 'billing_region', 'billing_city', 'billing_district',
                    'billing_address_line_1', 'billing_address_line_2', 'billing_postal_code', 'billing_reference',
                    'delivery_type', 'subtotal_before_discounts', 'discount_total', 'subtotal', 'shipping_total',
                    'tax_total', 'grand_total', 'tax_snapshot_version', 'taxable_amount', 'exempt_amount',
                    'unaffected_amount', 'tax_calculated_at',
                ]),
                'checkout_session_id' => $locked->getKey(),
                'order_number' => $this->numbers->handle($tenant),
                'status' => OrderStatus::Confirmed,
                'payment_status' => OrderPaymentStatus::Unpaid,
                'fulfillment_status' => OrderFulfillmentStatus::Unfulfilled,
                'placed_at' => now(),
            ]);
            foreach ($lines as $line) {
                $order->items()->create([
                    ...$line->only([
                        'tenant_id', 'store_id', 'store_listing_id', 'product_id', 'product_variant_id',
                        'product_name', 'variant_name', 'sku', 'quantity', 'price_list_id', 'base_unit_price',
                        'discount_unit_amount', 'final_unit_price', 'line_base_total', 'line_discount_total',
                        'line_total', 'promotion_id', 'promotion_name', 'tax_category_code', 'tax_rate',
                        'taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_amount',
                    ]),
                    'checkout_line_id' => $line->getKey(),
                ]);
            }
            $order->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => null, 'to_status' => OrderStatus::Confirmed,
                'actor_user_id' => $actor?->getKey(),
            ]);
            $locked->update(['status' => CheckoutStatus::Completed, 'completed_at' => now()]);
            $cart->update(['status' => CartStatus::Converted]);

            return $order->fresh(['items', 'history']);
        });
    }

    private function validateReservations(Tenant $tenant, $lines): void
    {
        $reservations = InventoryReservation::query()->where('tenant_id', $tenant->getKey())
            ->where('reference_type', CheckoutInventoryAllocator::REFERENCE_TYPE)
            ->whereIn('reference_id', $lines->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        foreach ($lines as $line) {
            $quantity = $reservations->where('reference_id', $line->getKey())
                ->where('status', InventoryReservationStatus::Active)->sum('quantity');
            if ($quantity !== $line->quantity) {
                throw ValidationException::withMessages([
                    'inventory' => "Active reservations no longer cover checkout line {$line->getKey()}.",
                ]);
            }
        }

        InventoryReservation::query()->where('tenant_id', $tenant->getKey())
            ->where('reference_type', CheckoutInventoryAllocator::REFERENCE_TYPE)
            ->whereIn('reference_id', $lines->pluck('id'))
            ->where('status', InventoryReservationStatus::Active)
            ->update(['expires_at' => null]);
    }

    private function validateTaxSnapshot(CheckoutSession $checkout, $lines): void
    {
        $totals = [
            'taxable_amount' => BigDecimal::zero(),
            'exempt_amount' => BigDecimal::zero(),
            'unaffected_amount' => BigDecimal::zero(),
            'tax_amount' => BigDecimal::zero(),
        ];

        foreach ($lines as $line) {
            if (in_array(null, [
                $line->tax_category_code, $line->tax_rate, $line->taxable_amount,
                $line->exempt_amount, $line->unaffected_amount, $line->tax_amount,
            ], true)) {
                throw ValidationException::withMessages(['tax' => 'CHECKOUT_TAX_SNAPSHOT_MISSING']);
            }
            foreach (array_keys($totals) as $field) {
                $totals[$field] = $totals[$field]->plus($line->{$field});
            }
        }

        $expected = [
            'taxable_amount' => $checkout->taxable_amount,
            'exempt_amount' => $checkout->exempt_amount,
            'unaffected_amount' => $checkout->unaffected_amount,
            'tax_amount' => $checkout->tax_total,
        ];
        foreach ($totals as $field => $total) {
            if (! $total->toScale(4)->isEqualTo(BigDecimal::of($expected[$field]))) {
                throw ValidationException::withMessages(['tax' => 'CHECKOUT_TAX_SNAPSHOT_INVALID']);
            }
        }
    }
}
