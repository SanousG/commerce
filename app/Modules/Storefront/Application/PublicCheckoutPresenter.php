<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;

final class PublicCheckoutPresenter
{
    public function __construct(
        private readonly PublicCartPresenter $carts,
        private readonly PublicPickupReadiness $pickupReadiness,
    ) {}

    /** @return array<string, mixed> */
    public function present(CheckoutSession $checkout, PublicStoreContext $context): array
    {
        $ready = $checkout->status === CheckoutStatus::Ready;
        $cart = $this->carts->present($checkout->cart, $context);
        $tenant = $context->tenant();
        $store = $context->store();
        abort_unless($tenant !== null && $store !== null, 404);
        $readiness = $ready && $checkout->pickup_inventory_location_id !== null
            ? $this->snapshotReadiness($checkout)
            : $this->pickupReadiness->evaluate($tenant, $store);
        if (is_array($readiness['pickup'])) {
            unset($readiness['pickup']['inventoryLocationId']);
        }
        $items = $ready
            ? $checkout->lines()->orderBy('created_at')->get()->map(fn ($line): array => [
                'productName' => $line->product_name,
                'variantName' => $line->variant_name,
                'quantity' => $line->quantity,
                'baseUnitPrice' => $line->base_unit_price,
                'finalUnitPrice' => $line->final_unit_price,
                'lineTotal' => $line->line_total,
            ])->values()->all()
            : collect($cart['items'])->map(fn (array $item): array => [
                'productName' => $item['productName'],
                'variantName' => $item['variantName'],
                'quantity' => $item['quantity'],
                'baseUnitPrice' => $item['baseUnitPrice'],
                'finalUnitPrice' => $item['finalUnitPrice'],
                'lineTotal' => $item['lineTotal'],
            ])->all();

        return [
            'status' => $checkout->status->value,
            'ready' => $ready,
            'expiresAt' => $ready ? $checkout->expires_at?->toIso8601String() : null,
            'currency' => $ready ? $checkout->currency : $cart['currency'],
            'itemCount' => $ready ? array_sum(array_column($items, 'quantity')) : $cart['itemCount'],
            'items' => $items,
            'totals' => [
                'subtotalBeforeDiscounts' => $ready ? $checkout->subtotal_before_discounts : $cart['subtotalBeforeDiscounts'],
                'discountTotal' => $ready ? $checkout->discount_total : $cart['discountTotal'],
                'subtotal' => $ready ? $checkout->subtotal : $cart['subtotal'],
                'shippingTotal' => $ready ? $checkout->shipping_total : null,
                'taxTotal' => $ready ? $checkout->tax_total : $cart['taxTotal'],
                'grandTotal' => $ready ? $checkout->grand_total : $cart['subtotal'],
            ],
            'buyer' => [
                'customerName' => $checkout->customer_name,
                'email' => $checkout->email,
                'phone' => $checkout->phone,
                'shippingRecipientName' => $checkout->shipping_recipient_name,
                'shippingPhone' => $checkout->shipping_phone,
                'shippingCountryCode' => $checkout->shipping_country_code ?? 'PE',
                'shippingRegion' => $checkout->shipping_region,
                'shippingCity' => $checkout->shipping_city,
                'shippingDistrict' => $checkout->shipping_district,
                'shippingAddressLine1' => $checkout->shipping_address_line_1,
                'shippingAddressLine2' => $checkout->shipping_address_line_2,
                'shippingPostalCode' => $checkout->shipping_postal_code,
                'shippingReference' => $checkout->shipping_reference,
                'billingRecipientName' => $checkout->billing_recipient_name,
                'billingPhone' => $checkout->billing_phone,
                'billingCountryCode' => $checkout->billing_country_code,
                'billingRegion' => $checkout->billing_region,
                'billingCity' => $checkout->billing_city,
                'billingDistrict' => $checkout->billing_district,
                'billingAddressLine1' => $checkout->billing_address_line_1,
                'billingAddressLine2' => $checkout->billing_address_line_2,
                'billingPostalCode' => $checkout->billing_postal_code,
                'billingReference' => $checkout->billing_reference,
            ],
            'commercial' => [
                'deliveryType' => 'PICKUP',
                'shippingConfigured' => false,
                'pickupConfigured' => $readiness['pickupAvailable'],
                'pickup' => $readiness['pickup'],
                'paymentMethods' => $readiness['paymentAvailable'] ? ['CASH'] : [],
                'payment' => $readiness['payment'],
                'orderCreationAvailable' => false,
                'restrictions' => $readiness['restrictions'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotReadiness(CheckoutSession $checkout): array
    {
        return [
            'pickupAvailable' => true,
            'paymentAvailable' => true,
            'restrictions' => [],
            'pickup' => [
                'name' => $checkout->pickup_name,
                'addressLine1' => $checkout->pickup_address_line_1,
                'addressLine2' => $checkout->pickup_address_line_2,
                'district' => $checkout->pickup_district,
                'province' => $checkout->pickup_province,
                'department' => $checkout->pickup_department,
                'countryCode' => $checkout->pickup_country_code,
                'postalCode' => $checkout->pickup_postal_code,
                'instructions' => $checkout->pickup_instructions,
            ],
            'payment' => [
                'expectedMethod' => $checkout->expected_payment_method?->value,
                'terms' => $checkout->payment_terms?->value,
                'depositType' => $checkout->cod_deposit_type?->value,
                'depositValue' => $checkout->cod_deposit_value,
            ],
        ];
    }
}
