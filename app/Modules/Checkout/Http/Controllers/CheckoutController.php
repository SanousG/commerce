<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Checkout\Application\CancelCheckout;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutLine;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Checkout\Http\Requests\ManageCheckoutRequest;
use App\Modules\Checkout\Http\Requests\StartCheckoutRequest;
use App\Modules\Checkout\Http\Requests\UpdateCheckoutRequest;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    private const ADDRESS_FIELDS = [
        'recipient_name', 'phone', 'country_code', 'region', 'city', 'district', 'address_line_1',
        'address_line_2', 'postal_code', 'reference',
    ];

    public function index(Request $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $checkouts = $stores->get()->checkouts()->where('tenant_id', $tenants->id())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('token', 'ilike', $search.'%')->orWhere('customer_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $checkouts->through(fn (CheckoutSession $checkout): array => [
            'id' => $checkout->getKey(), 'token_hint' => substr($checkout->token, 0, 8).'...',
            'customer' => $checkout->customer_name ?: 'Guest', 'status' => $checkout->status->value,
            'grand_total' => $checkout->grand_total, 'currency' => $checkout->currency,
            'expires_at' => $checkout->expires_at?->toISOString(), 'created_at' => $checkout->created_at?->toISOString(),
        ]);

        return Inertia::render('admin/checkouts/Index', [
            'checkouts' => $checkouts, 'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(TenantContext $tenants, StoreContext $stores): Response
    {
        $carts = $stores->get()->carts()->where('tenant_id', $tenants->id())->where('status', CartStatus::Active)
            ->whereHas('items')->whereDoesntHave('checkouts', fn ($query) => $query
            ->whereIn('status', [CheckoutStatus::Pending, CheckoutStatus::Ready]))
            ->with('customer')->orderByDesc('updated_at')->limit(100)->get()->map(fn ($cart): array => [
                'id' => $cart->getKey(), 'token_hint' => substr($cart->token, 0, 8).'...',
                'customer' => $cart->customer?->displayName() ?? 'Guest',
            ]);

        return Inertia::render('admin/checkouts/Prepare', ['carts' => $carts]);
    }

    public function store(StartCheckoutRequest $request, TenantContext $tenants, StoreContext $stores, StartCheckout $action): RedirectResponse
    {
        $cart = $stores->get()->carts()->where('tenant_id', $tenants->id())->findOrFail($request->validated('cart_id'));
        $checkout = $action->handle($tenants->get(), $stores->get(), $cart, $request->validated('idempotency_key'));

        return to_route('admin.checkouts.show', $checkout)->with('status', 'Checkout started. Add contact details and prepare it when ready.');
    }

    public function show(Request $request, TenantContext $tenants, StoreContext $stores, string $checkout): Response
    {
        $scoped = $this->checkout($tenants, $stores, $checkout);
        $scoped->load(['lines.reservations.location', 'customer.addresses', 'order']);

        return Inertia::render('admin/checkouts/Show', [
            'checkout' => $this->serialize($scoped),
            'addresses' => $scoped->status === CheckoutStatus::Pending
                ? $scoped->customer?->addresses->map(fn (CustomerAddress $address): array => array_merge(
                    ['id' => $address->getKey(), 'label' => $address->label], $address->only(self::ADDRESS_FIELDS),
                ))->values() ?? [] : [],
            'canManage' => $request->user()->can('checkouts.manage'),
            'canCreateOrder' => $request->user()->can('orders.manage'),
        ]);
    }

    public function update(UpdateCheckoutRequest $request, TenantContext $tenants, StoreContext $stores, UpdateCheckout $action, string $checkout): RedirectResponse
    {
        $scoped = $this->checkout($tenants, $stores, $checkout);
        $data = collect($request->validated())->except(['shipping_address_id', 'billing_address_id', 'billing_same_as_shipping'])->all();
        foreach (['shipping', 'billing'] as $type) {
            $addressId = $request->validated("{$type}_address_id");
            if (filled($addressId)) {
                abort_if($scoped->customer_id === null, 404);
                $address = $tenants->get()->customerAddresses()->where('customer_id', $scoped->customer_id)->findOrFail($addressId);
                foreach (self::ADDRESS_FIELDS as $field) {
                    $data["{$type}_{$field}"] = $address->{$field};
                }
            }
        }
        if ($request->boolean('billing_same_as_shipping')) {
            foreach (self::ADDRESS_FIELDS as $field) {
                $data["billing_{$field}"] = $data["shipping_{$field}"] ?? $scoped->{"shipping_{$field}"};
            }
        }
        $action->handle($tenants->get(), $scoped, $data);

        return back()->with('status', 'Checkout details updated.');
    }

    public function prepare(ManageCheckoutRequest $request, TenantContext $tenants, StoreContext $stores, PrepareCheckout $action, string $checkout): RedirectResponse
    {
        $action->handle($tenants->get(), $this->checkout($tenants, $stores, $checkout));

        return back()->with('status', 'Checkout prepared and inventory reserved.');
    }

    public function cancel(ManageCheckoutRequest $request, TenantContext $tenants, StoreContext $stores, CancelCheckout $action, string $checkout): RedirectResponse
    {
        $action->handle($tenants->get(), $this->checkout($tenants, $stores, $checkout));

        return back()->with('status', 'Checkout cancelled and active reservations released.');
    }

    private function checkout(TenantContext $tenants, StoreContext $stores, string $id): CheckoutSession
    {
        return $stores->get()->checkouts()->where('tenant_id', $tenants->id())->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function serialize(CheckoutSession $checkout): array
    {
        $addresses = [];
        foreach (['shipping', 'billing'] as $type) {
            $addresses[$type] = collect(self::ADDRESS_FIELDS)->mapWithKeys(
                fn (string $field): array => [$field => $checkout->{"{$type}_{$field}"}],
            )->all();
        }

        return [
            'id' => $checkout->getKey(), 'token_hint' => substr($checkout->token, 0, 8).'...',
            'cart_id' => $checkout->cart_id, 'customer_id' => $checkout->customer_id,
            'status' => $checkout->status->value, 'delivery_type' => $checkout->delivery_type->value,
            'currency' => $checkout->currency, 'customer_name' => $checkout->customer_name,
            'email' => $checkout->email, 'phone' => $checkout->phone, ...$addresses,
            'subtotal_before_discounts' => $checkout->subtotal_before_discounts,
            'discount_total' => $checkout->discount_total, 'subtotal' => $checkout->subtotal,
            'shipping_total' => $checkout->shipping_total, 'tax_total' => $checkout->tax_total,
            'taxable_amount' => $checkout->taxable_amount, 'exempt_amount' => $checkout->exempt_amount,
            'unaffected_amount' => $checkout->unaffected_amount,
            'tax_calculated_at' => $checkout->tax_calculated_at?->toISOString(),
            'grand_total' => $checkout->grand_total, 'expires_at' => $checkout->expires_at?->toISOString(),
            'created_at' => $checkout->created_at?->toISOString(),
            'order' => $checkout->order === null ? null : [
                'id' => $checkout->order->getKey(), 'order_number' => $checkout->order->order_number,
            ],
            'lines' => $checkout->lines->map(fn (CheckoutLine $line): array => [
                'id' => $line->getKey(), 'product_name' => $line->product_name, 'variant_name' => $line->variant_name,
                'sku' => $line->sku, 'quantity' => $line->quantity, 'base_unit_price' => $line->base_unit_price,
                'discount_unit_amount' => $line->discount_unit_amount, 'final_unit_price' => $line->final_unit_price,
                'line_base_total' => $line->line_base_total, 'line_discount_total' => $line->line_discount_total,
                'line_total' => $line->line_total, 'promotion_name' => $line->promotion_name,
                'tax_category_code' => $line->tax_category_code, 'tax_rate' => $line->tax_rate,
                'taxable_amount' => $line->taxable_amount, 'exempt_amount' => $line->exempt_amount,
                'unaffected_amount' => $line->unaffected_amount, 'tax_amount' => $line->tax_amount,
                'allocations' => $line->reservations->map(fn ($reservation): array => [
                    'location' => $reservation->location->name, 'code' => $reservation->location->code,
                    'quantity' => $reservation->quantity, 'status' => $reservation->status->value,
                ])->values(),
            ])->values(),
        ];
    }
}
