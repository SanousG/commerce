<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Models\OrderPaymentPlan;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigureOrderPaymentTerms
{
    public function __construct(
        private readonly CalculateCodDeposit $cod,
        private readonly OrderPaymentCalculator $calculator,
    ) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        Order $order,
        PaymentTerms $terms,
        ?PaymentMethod $expectedMethod = null,
    ): OrderPaymentPlan
    {
        return DB::transaction(function () use ($tenant, $store, $order, $terms, $expectedMethod): OrderPaymentPlan {
            $locked = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['payment_terms' => 'Only confirmed orders can change payment terms.']);
            }
            if ($this->calculator->calculate($locked)->paymentStatus !== OrderPaymentStatus::Unpaid) {
                throw ValidationException::withMessages(['payment_terms' => 'Payment terms cannot change after a capture.']);
            }

            $values = [
                'terms' => $terms, 'currency' => $locked->currency, 'total_amount' => $locked->grand_total,
                'amount_due_now' => $locked->grand_total, 'amount_due_later' => '0.0000',
                'deposit_type' => null, 'deposit_value' => null,
                'expected_method' => $expectedMethod,
            ];
            $settings = null;
            if ($expectedMethod !== null) {
                $settings = StorePaymentSettings::query()->where('tenant_id', $tenant->getKey())
                    ->where('store_id', $store->getKey())->first();
                $enabled = match ($expectedMethod) {
                    PaymentMethod::Cash => $settings?->cash_enabled,
                    PaymentMethod::BankTransfer => $settings?->bank_transfer_enabled,
                    PaymentMethod::Online => $settings?->online_enabled,
                };
                if (! $enabled) {
                    throw ValidationException::withMessages(['payment_terms' => 'The expected payment method is not enabled for this store.']);
                }
            }
            if ($terms === PaymentTerms::Cod) {
                $settings ??= StorePaymentSettings::query()->where('tenant_id', $tenant->getKey())
                    ->where('store_id', $store->getKey())->first();
                if ($settings === null || ! $settings->cod_enabled) {
                    throw ValidationException::withMessages(['payment_terms' => 'COD is not enabled for this store.']);
                }
                $deposit = $this->cod->calculate(
                    $locked->grand_total,
                    $settings->cod_deposit_type,
                    $settings->cod_deposit_value,
                );
                $values = [...$values, ...$deposit];
            }

            return OrderPaymentPlan::query()->updateOrCreate(
                ['order_id' => $locked->getKey()],
                ['tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), ...$values],
            );
        });
    }
}
