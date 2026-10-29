<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Application\ExpireCheckout;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Checkout\Domain\Enums\CheckoutDeliveryType;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicCheckout
{
    public function __construct(
        private readonly CartCalculator $calculator,
        private readonly StartCheckout $start,
        private readonly UpdateCheckout $update,
        private readonly PrepareCheckout $prepare,
        private readonly ExpireCheckout $expire,
        private readonly PublicPickupReadiness $pickupReadiness,
    ) {}

    public function operational(PublicStoreContext $context, Cart $cart): ?CheckoutSession
    {
        $store = $context->store();
        $tenant = $context->tenant();
        abort_unless($store !== null && $tenant !== null, 404);

        $checkout = CheckoutSession::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey())
            ->where('cart_id', $cart->getKey())
            ->whereIn('status', [CheckoutStatus::Pending, CheckoutStatus::Ready])
            ->latest('created_at')
            ->first();

        if ($checkout?->status === CheckoutStatus::Ready
            && $checkout->expires_at?->lessThanOrEqualTo(now())) {
            $this->expire->handle($tenant, $checkout);

            return null;
        }

        return $checkout;
    }

    public function start(PublicStoreContext $context, Cart $cart, string $mutationId): CheckoutSession
    {
        $store = $context->store();
        $tenant = $context->tenant();
        abort_unless($store !== null && $tenant !== null, 404);

        $existing = $this->operational($context, $cart);
        if ($existing !== null) {
            return $existing;
        }

        $summary = $this->calculator->calculate($cart);
        if ($summary->itemCount === 0 || ! $summary->valid || $summary->currency === null) {
            throw ValidationException::withMessages([
                'cart' => __('The cart must be valid before checkout can begin.'),
            ]);
        }

        return DB::transaction(function () use ($tenant, $store, $cart, $mutationId): CheckoutSession {
            $this->pickupReadiness->requireAvailable($tenant, $store, true);

            return $this->start->handle($tenant, $store, $cart, 'public:'.$mutationId);
        });
    }

    /** @param array<string, mixed> $data */
    public function prepare(Request $request, PublicStoreContext $context, Cart $cart, array $data): CheckoutSession
    {
        $tenant = $context->tenant();
        $store = $context->store();
        abort_unless($tenant !== null && $store !== null, 404);
        $checkout = $this->operational($context, $cart);
        abort_unless($checkout !== null, 404);
        $mutationId = (string) $data['mutation_id'];
        unset($data['mutation_id']);
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $sessionKey = 'storefront.checkout_mutations.'.hash('sha256', (string) $cart->getKey());
        $completed = $request->session()->get($sessionKey, []);
        if (is_array($completed) && isset($completed[$mutationId])) {
            if (! hash_equals((string) $completed[$mutationId], $fingerprint)) {
                throw ValidationException::withMessages([
                    'checkout' => __('This checkout submission was already used with different data.'),
                ]);
            }

            return $checkout;
        }

        if ($checkout->status === CheckoutStatus::Ready) {
            return $checkout;
        }

        $checkout = DB::transaction(function () use ($tenant, $store, $checkout, $data): CheckoutSession {
            $readiness = $this->pickupReadiness->requireAvailable($tenant, $store, true);
            $pickup = $readiness['pickup'];
            $payment = $readiness['payment'];
            $snapshot = [...$data, ...array_fill_keys([
                'shipping_recipient_name', 'shipping_phone', 'shipping_country_code', 'shipping_region', 'shipping_city',
                'shipping_district', 'shipping_address_line_1', 'shipping_address_line_2', 'shipping_postal_code',
                'shipping_reference',
            ], null), ...[
                'delivery_type' => CheckoutDeliveryType::Pickup->value,
                'pickup_inventory_location_id' => $pickup['inventoryLocationId'],
                'pickup_name' => $pickup['name'],
                'pickup_address_line_1' => $pickup['addressLine1'],
                'pickup_address_line_2' => $pickup['addressLine2'],
                'pickup_district' => $pickup['district'],
                'pickup_province' => $pickup['province'],
                'pickup_department' => $pickup['department'],
                'pickup_country_code' => $pickup['countryCode'],
                'pickup_postal_code' => $pickup['postalCode'],
                'pickup_instructions' => $pickup['instructions'],
                'expected_payment_method' => $payment['expectedMethod'],
                'payment_terms' => $payment['terms'],
                'cod_deposit_type' => $payment['depositType'],
                'cod_deposit_value' => $payment['depositValue'],
            ]];

            return $this->prepare->handle($tenant, $this->update->handle($tenant, $checkout, $snapshot));
        });
        $completed = is_array($completed) ? $completed : [];
        $completed[$mutationId] = $fingerprint;
        $request->session()->put($sessionKey, array_slice($completed, -25, null, true));

        return $checkout;
    }
}
