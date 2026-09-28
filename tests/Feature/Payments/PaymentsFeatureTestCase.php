<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\CreateManualPayment;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\Orders\OrdersFeatureTestCase;

abstract class PaymentsFeatureTestCase extends OrdersFeatureTestCase
{
    protected function enablePayments(
        Tenant $tenant,
        Store $store,
        CodDepositType $depositType = CodDepositType::None,
        string $depositValue = '0.0000',
    ): StorePaymentSettings {
        return StorePaymentSettings::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
            'bank_transfer_enabled' => true, 'cash_enabled' => true, 'online_enabled' => false,
            'cod_enabled' => true, 'cod_deposit_type' => $depositType, 'cod_deposit_value' => $depositValue,
        ]);
    }

    protected function manualPayment(
        Tenant $tenant,
        Store $store,
        Order $order,
        string $amount,
        ?User $actor = null,
        PaymentMethod $method = PaymentMethod::BankTransfer,
    ): Payment {
        return app(CreateManualPayment::class)->handle(
            $tenant,
            $store,
            $order,
            $method,
            $amount,
            (string) Str::uuid(),
            null,
            $actor,
        );
    }
}
