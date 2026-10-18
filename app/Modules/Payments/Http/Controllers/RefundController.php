<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\CompleteManualRefund;
use App\Modules\Payments\Application\CreateRefund;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Http\Requests\CompleteManualRefundRequest;
use App\Modules\Payments\Http\Requests\CreateRefundRequest;
use App\Modules\Payments\Http\Requests\RefundIndexRequest;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(RefundIndexRequest $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? '';
        $orderId = $filters['order_id'] ?? '';
        $refunds = Refund::query()->where('tenant_id', $tenants->id())->where('store_id', $stores->id())
            ->when($search !== '', fn ($query) => $query->whereHas('order', fn ($query) => $query
                ->where('order_number', 'ilike', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($orderId !== '', fn ($query) => $query->where('order_id', $orderId))
            ->with(['order:id,order_number', 'payment:id,method,provider'])
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $refunds->through(fn (Refund $refund): array => $this->serialize($refund));

        return Inertia::render('admin/refunds/Index', [
            'refunds' => $refunds,
            'filters' => ['search' => $search, 'status' => $status, 'order_id' => $orderId],
            'statuses' => array_column(RefundStatus::cases(), 'value'),
        ]);
    }

    public function show(TenantContext $tenants, StoreContext $stores, string $refund): Response
    {
        $scoped = $this->refund($tenants, $stores, $refund);
        $scoped->load(['order:id,order_number', 'payment.transactions', 'returnRequest:id,status']);
        $sum = fn (PaymentTransactionType $type): BigDecimal => $scoped->payment->transactions
            ->where('type', $type)->where('status', PaymentTransactionStatus::Succeeded)
            ->reduce(fn (BigDecimal $total, $transaction): BigDecimal => $total->plus($transaction->amount), BigDecimal::zero())
            ->toScale(4);
        $captured = $sum(PaymentTransactionType::Capture);
        $refunded = $sum(PaymentTransactionType::Refund);

        return Inertia::render('admin/refunds/Show', [
            'refund' => $this->serialize($scoped),
            'balance' => [
                'captured_total' => (string) $captured,
                'refunded_total' => (string) $refunded,
                'refundable_remaining' => (string) $captured->minus($refunded)->toScale(4),
            ],
            'capabilities' => ['complete' => $scoped->status === RefundStatus::Pending],
        ]);
    }

    public function store(
        CreateRefundRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateRefund $action,
        string $payment,
    ): RedirectResponse {
        $refund = $action->handle(
            $tenants->get(), $stores->get(), $this->payment($tenants, $stores, $payment),
            $request->validated('amount'), $request->validated('reason'),
            $request->validated('idempotency_key'),
            $request->validated('return_id') === null ? null : ReturnRequest::query()
                ->where('tenant_id', $tenants->id())->where('store_id', $stores->id())
                ->findOrFail($request->validated('return_id')),
            $request->user(),
        );

        return back()->with('status', "Refund {$refund->getKey()} created pending completion.");
    }

    public function complete(
        CompleteManualRefundRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CompleteManualRefund $action,
        string $refund,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->refund($tenants, $stores, $refund),
            $request->validated('idempotency_key'), $request->validated('external_reference'), $request->user(),
        );

        return back()->with('status', 'Refund completed.');
    }

    private function payment(TenantContext $tenants, StoreContext $stores, string $id): Payment
    {
        return Payment::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function refund(TenantContext $tenants, StoreContext $stores, string $id): Refund
    {
        return Refund::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function serialize(Refund $refund): array
    {
        return [
            'id' => $refund->getKey(), 'order_id' => $refund->order_id,
            'order_number' => $refund->order?->order_number, 'payment_id' => $refund->payment_id,
            'payment_method' => $refund->payment?->method?->value, 'payment_provider' => $refund->payment?->provider?->value,
            'return_id' => $refund->return_id, 'status' => $refund->status->value,
            'amount' => $refund->amount, 'currency' => $refund->currency, 'reason' => $refund->reason,
            'external_reference' => $refund->external_reference,
            'completed_at' => $refund->completed_at?->toISOString(), 'created_at' => $refund->created_at?->toISOString(),
        ];
    }
}
