<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\CreateOrderFromCheckout;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Orders\Domain\Models\OrderStatusHistory;
use App\Modules\Orders\Http\Requests\CancelOrderRequest;
use App\Modules\Orders\Http\Requests\CreateOrderRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const ADDRESS_FIELDS = [
        'recipient_name', 'phone', 'country_code', 'region', 'city', 'district', 'address_line_1',
        'address_line_2', 'postal_code', 'reference',
    ];

    public function index(Request $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $search = trim((string) $request->query('search'));
        $status = $this->enumFilter((string) $request->query('status'), OrderStatus::cases());
        $paymentStatus = $this->enumFilter((string) $request->query('payment_status'), OrderPaymentStatus::cases());
        $fulfillmentStatus = $this->enumFilter((string) $request->query('fulfillment_status'), OrderFulfillmentStatus::cases());
        $orders = $stores->get()->orders()->where('tenant_id', $tenants->id())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('order_number', 'ilike', "%{$search}%")
                ->orWhere('customer_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when($fulfillmentStatus !== '', fn ($query) => $query->where('fulfillment_status', $fulfillmentStatus))
            ->orderByDesc('placed_at')->paginate(20)->withQueryString();
        $orders->through(fn (Order $order): array => [
            'id' => $order->getKey(), 'order_number' => $order->order_number,
            'customer_name' => $order->customer_name ?: 'Guest', 'email' => $order->email,
            'status' => $order->status->value, 'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value, 'currency' => $order->currency,
            'grand_total' => $order->grand_total, 'placed_at' => $order->placed_at->toISOString(),
        ]);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search, 'status' => $status, 'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus,
            ],
        ]);
    }

    public function show(Request $request, TenantContext $tenants, StoreContext $stores, string $order): Response
    {
        $scoped = $this->order($tenants, $stores, $order);
        $scoped->load(['items.reservations.location', 'history.actor']);

        return Inertia::render('admin/orders/Show', [
            'order' => $this->serialize($scoped),
            'canCancel' => $request->user()->can('orders.manage')
                && $scoped->status === OrderStatus::Confirmed
                && $scoped->fulfillment_status === OrderFulfillmentStatus::Unfulfilled,
        ]);
    }

    public function storeFromCheckout(
        CreateOrderRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateOrderFromCheckout $action,
        string $checkout,
    ): RedirectResponse {
        $scoped = $stores->get()->checkouts()->where('tenant_id', $tenants->id())->findOrFail($checkout);
        $order = $action->handle($tenants->get(), $stores->get(), $scoped, $request->user());

        return to_route('admin.orders.show', $order)->with('status', "Order {$order->order_number} created.");
    }

    public function cancel(
        CancelOrderRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CancelOrder $action,
        string $order,
    ): RedirectResponse {
        $scoped = $this->order($tenants, $stores, $order);
        $action->handle($tenants->get(), $scoped, $request->validated('reason'), $request->user());

        return back()->with('status', 'Order cancelled and active reservations released.');
    }

    private function order(TenantContext $tenants, StoreContext $stores, string $id): Order
    {
        return $stores->get()->orders()->where('tenant_id', $tenants->id())->findOrFail($id);
    }

    private function enumFilter(string $value, array $cases): string
    {
        $value = trim($value);

        return collect($cases)->contains(fn ($case): bool => $case->value === $value) ? $value : '';
    }

    private function serialize(Order $order): array
    {
        $addresses = [];
        foreach (['shipping', 'billing'] as $type) {
            $addresses[$type] = collect(self::ADDRESS_FIELDS)->mapWithKeys(
                fn (string $field): array => [$field => $order->{"{$type}_{$field}"}],
            )->all();
        }

        return [
            'id' => $order->getKey(), 'order_number' => $order->order_number,
            'checkout_session_id' => $order->checkout_session_id, 'cart_id' => $order->cart_id,
            'customer_id' => $order->customer_id, 'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'currency' => $order->currency, 'customer_name' => $order->customer_name,
            'email' => $order->email, 'phone' => $order->phone, ...$addresses,
            'delivery_type' => $order->delivery_type,
            'subtotal_before_discounts' => $order->subtotal_before_discounts,
            'discount_total' => $order->discount_total, 'subtotal' => $order->subtotal,
            'shipping_total' => $order->shipping_total, 'tax_total' => $order->tax_total,
            'grand_total' => $order->grand_total, 'placed_at' => $order->placed_at->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->getKey(), 'product_name' => $item->product_name,
                'variant_name' => $item->variant_name, 'sku' => $item->sku, 'quantity' => $item->quantity,
                'base_unit_price' => $item->base_unit_price,
                'discount_unit_amount' => $item->discount_unit_amount,
                'final_unit_price' => $item->final_unit_price, 'line_base_total' => $item->line_base_total,
                'line_discount_total' => $item->line_discount_total, 'line_total' => $item->line_total,
                'promotion_name' => $item->promotion_name,
                'reservations' => $item->reservations->map(fn ($reservation): array => [
                    'id' => $reservation->getKey(), 'location' => $reservation->location->name,
                    'code' => $reservation->location->code, 'quantity' => $reservation->quantity,
                    'status' => $reservation->status->value,
                ])->values(),
            ])->values(),
            'history' => $order->history->map(fn (OrderStatusHistory $entry): array => [
                'id' => $entry->getKey(), 'from_status' => $entry->from_status?->value,
                'to_status' => $entry->to_status->value, 'reason' => $entry->reason,
                'actor' => $entry->actor?->name, 'created_at' => $entry->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
