<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\CancelPayment;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Application\CreateManualPayment;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Payments\Http\Requests\CancelPaymentRequest;
use App\Modules\Payments\Http\Requests\CaptureManualPaymentRequest;
use App\Modules\Payments\Http\Requests\CreateManualPaymentRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, TenantContext $tenants, StoreContext $stores): Response
    {
        $payments = $stores->get()->payments()->where('tenant_id', $tenants->id())
            ->with('order:id,order_number')
            ->withSum(['transactions as captured_amount' => fn ($query) => $query
                ->where('type', PaymentTransactionType::Capture->value)
                ->where('status', PaymentTransactionStatus::Succeeded->value)], 'amount')
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $payments->through(fn (Payment $payment): array => $this->serialize($payment));

        return Inertia::render('admin/payments/Index', ['payments' => $payments]);
    }

    public function show(TenantContext $tenants, StoreContext $stores, string $payment): Response
    {
        $scoped = $this->payment($tenants, $stores, $payment);
        $scoped->load(['order:id,order_number', 'transactions.actor']);

        return Inertia::render('admin/payments/Show', ['payment' => $this->serialize($scoped, true)]);
    }

    public function store(
        CreateManualPaymentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateManualPayment $action,
        string $order,
    ): RedirectResponse {
        $scoped = $stores->get()->orders()->where('tenant_id', $tenants->id())->findOrFail($order);
        $payment = $action->handle(
            $tenants->get(), $stores->get(), $scoped, PaymentMethod::from($request->validated('method')),
            $request->validated('amount'), $request->validated('idempotency_key'),
            $request->validated('external_reference'), $request->user(),
        );

        return back()->with('status', "Payment {$payment->getKey()} created pending confirmation.");
    }

    public function capture(
        CaptureManualPaymentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CaptureManualPayment $action,
        string $payment,
    ): RedirectResponse {
        $scoped = $this->payment($tenants, $stores, $payment);
        $action->handle(
            $tenants->get(), $stores->get(), $scoped, $request->validated('amount'),
            $request->validated('idempotency_key'), $request->validated('external_reference'), $request->user(),
        );

        return back()->with('status', 'Payment captured.');
    }

    public function cancel(
        CancelPaymentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CancelPayment $action,
        string $payment,
    ): RedirectResponse {
        $action->handle($tenants->get(), $stores->get(), $this->payment($tenants, $stores, $payment));

        return back()->with('status', 'Payment cancelled.');
    }

    private function payment(TenantContext $tenants, StoreContext $stores, string $id): Payment
    {
        return $stores->get()->payments()->where('tenant_id', $tenants->id())->findOrFail($id);
    }

    private function serialize(Payment $payment, bool $withTransactions = false): array
    {
        $captured = $payment->getAttribute('captured_amount');
        if ($captured === null && $payment->relationLoaded('transactions')) {
            $captured = $payment->transactions->where('type', PaymentTransactionType::Capture)
                ->where('status', PaymentTransactionStatus::Succeeded)
                ->reduce(fn (BigDecimal $total, PaymentTransaction $transaction): BigDecimal => $total->plus($transaction->amount), BigDecimal::zero());
        }

        return [
            'id' => $payment->getKey(), 'order_id' => $payment->order_id,
            'order_number' => $payment->order?->order_number, 'method' => $payment->method->value,
            'provider' => $payment->provider->value, 'status' => $payment->status->value,
            'currency' => $payment->currency, 'amount' => $payment->amount,
            'captured_amount' => (string) BigDecimal::of((string) ($captured ?? '0'))->toScale(4),
            'external_reference' => $this->maskReference($payment->external_reference),
            'created_at' => $payment->created_at?->toISOString(),
            'transactions' => $withTransactions ? $payment->transactions->map(fn (PaymentTransaction $transaction): array => [
                'id' => $transaction->getKey(), 'type' => $transaction->type->value,
                'status' => $transaction->status->value, 'amount' => $transaction->amount,
                'currency' => $transaction->currency, 'actor' => $transaction->actor?->name,
                'occurred_at' => $transaction->occurred_at->toISOString(),
            ])->values() : [],
        ];
    }

    private function maskReference(?string $reference): ?string
    {
        return $reference === null ? null : '***'.mb_substr($reference, -4);
    }
}
