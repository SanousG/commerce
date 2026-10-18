<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Costing\Application\OrderProfitability;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fulfillment\Application\FulfillmentPaymentEligibility;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\FulfillmentItem;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
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
use App\Modules\Payments\Application\OrderPaymentCalculator;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
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

    public function show(
        Request $request,
        TenantContext $tenants,
        StoreContext $stores,
        OrderPaymentCalculator $paymentCalculator,
        FulfillmentPaymentEligibility $fulfillmentPaymentEligibility,
        OrderProfitability $profitability,
        string $order,
    ): Response {
        $scoped = $this->order($tenants, $stores, $order);
        $scoped->load(['items', 'history.actor']);
        $canViewPayments = $request->user()->can('payments.view');
        $canViewFulfillments = $request->user()->can('fulfillments.view');
        $canViewFiscalDocuments = $request->user()->can('fiscal_documents.view');
        $balance = $paymentCalculator->calculate($scoped);
        if ($canViewPayments) {
            $scoped->load(['paymentPlan', 'payments.transactions']);
        }
        if ($canViewFulfillments) {
            $scoped->load('items.reservations.location');
        }

        return Inertia::render('admin/orders/Show', [
            'order' => $this->serialize($scoped, $canViewFulfillments),
            'canCancel' => $request->user()->can('orders.manage')
                && $scoped->status === OrderStatus::Confirmed
                && $scoped->fulfillment_status === OrderFulfillmentStatus::Unfulfilled
                && $balance->capturedTotal === '0.0000',
            'paymentData' => $canViewPayments ? $this->paymentData($scoped, $balance->toArray(), $request) : null,
            'fulfillmentData' => $canViewFulfillments
                ? $this->fulfillmentData($scoped, $fulfillmentPaymentEligibility, $request)
                : null,
            'fiscalData' => $canViewFiscalDocuments ? $this->fiscalData($scoped, $request) : null,
            'profitability' => $request->user()->can('inventory.cost.view') ? $profitability->build($scoped) : null,
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

    private function serialize(Order $order, bool $withReservations = true): array
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
            'taxable_amount' => $order->taxable_amount, 'exempt_amount' => $order->exempt_amount,
            'unaffected_amount' => $order->unaffected_amount,
            'tax_calculated_at' => $order->tax_calculated_at?->toISOString(),
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
                'tax_category_code' => $item->tax_category_code, 'tax_rate' => $item->tax_rate,
                'taxable_amount' => $item->taxable_amount, 'exempt_amount' => $item->exempt_amount,
                'unaffected_amount' => $item->unaffected_amount, 'tax_amount' => $item->tax_amount,
                'reservations' => ! $withReservations ? [] : $item->reservations->map(fn ($reservation): array => [
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

    private function paymentData(Order $order, array $balance, Request $request): array
    {
        $plan = $order->paymentPlan;
        $settings = StorePaymentSettings::query()->where('tenant_id', $order->tenant_id)
            ->where('store_id', $order->store_id)->first();
        $acceptsPayments = $order->status === OrderStatus::Confirmed;

        return [
            'balance' => $balance,
            'plan' => $plan === null ? [
                'terms' => PaymentTerms::Prepaid->value, 'total_amount' => $order->grand_total,
                'amount_due_now' => $order->grand_total, 'amount_due_later' => '0.0000',
                'deposit_type' => null, 'deposit_value' => null, 'persisted' => false,
            ] : [
                'terms' => $plan->terms->value, 'total_amount' => $plan->total_amount,
                'amount_due_now' => $plan->amount_due_now, 'amount_due_later' => $plan->amount_due_later,
                'deposit_type' => $plan->deposit_type?->value, 'deposit_value' => $plan->deposit_value,
                'persisted' => true,
            ],
            'enabled_methods' => [
                'BANK_TRANSFER' => $settings?->bank_transfer_enabled ?? false,
                'CASH' => $settings?->cash_enabled ?? false,
            ],
            'can_manage' => $request->user()->can('payments.manage') && $acceptsPayments,
            'can_capture' => $request->user()->can('payments.capture'),
            'payments' => $order->payments->map(fn (Payment $payment): array => [
                'id' => $payment->getKey(), 'method' => $payment->method->value,
                'provider' => $payment->provider->value, 'status' => $payment->status->value,
                'amount' => $payment->amount, 'currency' => $payment->currency,
                'external_reference' => $payment->external_reference === null
                    ? null : '***'.mb_substr($payment->external_reference, -4),
                'captured_amount' => (string) $payment->transactions
                    ->where('type', PaymentTransactionType::Capture)
                    ->where('status', PaymentTransactionStatus::Succeeded)
                    ->reduce(fn (BigDecimal $total, PaymentTransaction $transaction): BigDecimal => $total->plus($transaction->amount), BigDecimal::zero())
                    ->toScale(4),
                'can_capture' => $request->user()->can('payments.capture') && $acceptsPayments
                    && $payment->status === PaymentStatus::Pending,
                'can_cancel' => $request->user()->can('payments.manage') && $payment->status === PaymentStatus::Pending,
                'transactions' => $payment->transactions->map(fn (PaymentTransaction $transaction): array => [
                    'id' => $transaction->getKey(), 'type' => $transaction->type->value,
                    'status' => $transaction->status->value, 'amount' => $transaction->amount,
                    'occurred_at' => $transaction->occurred_at->toISOString(),
                ])->values(),
            ])->values(),
        ];
    }

    private function fiscalData(Order $order, Request $request): array
    {
        $documents = FiscalDocument::query()->where('tenant_id', $order->tenant_id)
            ->where('store_id', $order->store_id)->where('order_id', $order->getKey())
            ->orderByDesc('created_at')->get();
        $primary = $documents->first(fn (FiscalDocument $document): bool => in_array(
            $document->document_type,
            [FiscalDocumentType::Invoice, FiscalDocumentType::Receipt],
            true,
        ));

        return [
            'can_issue' => $request->user()->can('fiscal_documents.issue')
                && $order->status === OrderStatus::Confirmed
                && $order->tax_snapshot_version === 1
                && $primary === null,
            'documents' => $documents->map(fn (FiscalDocument $document): array => [
                'id' => $document->getKey(), 'full_number' => $document->full_number,
                'document_type' => $document->document_type->value, 'status' => $document->status->value,
                'grand_total' => $document->grand_total,
            ])->values(),
        ];
    }

    private function fulfillmentData(
        Order $order,
        FulfillmentPaymentEligibility $paymentEligibility,
        Request $request,
    ): array {
        $fulfillments = Fulfillment::query()->where('tenant_id', $order->tenant_id)
            ->where('store_id', $order->store_id)->where('order_id', $order->getKey())
            ->with(['location:id,name,code', 'items:id,fulfillment_id,quantity', 'shipment:id,fulfillment_id,status'])
            ->orderByDesc('created_at')->get();
        $reservationIds = $order->items->flatMap(fn (OrderItem $item) => $item->reservations)->pluck('id');
        $allocated = FulfillmentItem::query()->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
            ->where('fulfillment_items.tenant_id', $order->tenant_id)
            ->where('fulfillment_items.store_id', $order->store_id)
            ->where('fulfillment_items.order_id', $order->getKey())
            ->where('fulfillments.status', '!=', FulfillmentStatus::Cancelled->value)
            ->whereIn('inventory_reservation_id', $reservationIds)
            ->groupBy('inventory_reservation_id')
            ->selectRaw('inventory_reservation_id, SUM(fulfillment_items.quantity) AS quantity')
            ->pluck('quantity', 'inventory_reservation_id');
        $available = $order->items->flatMap(fn (OrderItem $item) => $item->reservations
            ->filter(fn ($reservation): bool => $reservation->status === InventoryReservationStatus::Active
                && ($reservation->expires_at === null || $reservation->expires_at->isFuture()))
            ->map(function ($reservation) use ($item, $allocated): ?array {
                $remaining = $reservation->quantity - (int) ($allocated[$reservation->getKey()] ?? 0);

                return $remaining <= 0 ? null : [
                    'inventory_reservation_id' => $reservation->getKey(), 'order_item_id' => $item->getKey(),
                    'product_name' => $item->product_name, 'variant_name' => $item->variant_name,
                    'sku' => $item->sku, 'location_id' => $reservation->inventory_location_id,
                    'location_name' => $reservation->location->name, 'location_code' => $reservation->location->code,
                    'remaining_quantity' => $remaining,
                ];
            }))->filter()->groupBy('location_id')->map(fn ($reservations, string $locationId): array => [
                'id' => $locationId, 'name' => $reservations->first()['location_name'],
                'code' => $reservations->first()['location_code'], 'items' => $reservations->values(),
            ])->values();
        $eligibility = $paymentEligibility->evaluate($order);
        $orderedQuantity = (int) $order->items->sum('quantity');
        $fulfilledQuantity = (int) $fulfillments
            ->where('status', FulfillmentStatus::Fulfilled)
            ->sum(fn (Fulfillment $fulfillment): int => (int) $fulfillment->items->sum('quantity'));

        return [
            'summary' => [
                'ordered_quantity' => $orderedQuantity,
                'fulfilled_quantity' => $fulfilledQuantity,
                'remaining_quantity' => $orderedQuantity - $fulfilledQuantity,
                'status' => $order->fulfillment_status->value,
            ],
            'fulfillments' => $fulfillments->map(fn (Fulfillment $fulfillment): array => [
                'id' => $fulfillment->getKey(), 'status' => $fulfillment->status->value,
                'location_name' => $fulfillment->location->name, 'location_code' => $fulfillment->location->code,
                'item_count' => $fulfillment->items->count(), 'total_quantity' => $fulfillment->items->sum('quantity'),
                'shipment_status' => $fulfillment->shipment?->status->value,
                'created_at' => $fulfillment->created_at?->toISOString(),
            ])->values(),
            'available_locations' => $available,
            'payment_eligibility' => $eligibility,
            'can_manage' => $request->user()->can('fulfillments.manage'),
            'can_complete' => $request->user()->can('fulfillments.complete'),
            'can_manage_shipments' => $request->user()->can('shipments.manage'),
        ];
    }
}
