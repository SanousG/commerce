<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Payments\Application\CalculateCodDeposit;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Application\ConfigureOrderPaymentTerms;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use Illuminate\Validation\ValidationException;

class PaymentPlanAndSettingsTest extends PaymentsFeatureTestCase
{
    public function test_cod_calculation_uses_scale_four_and_half_up(): void
    {
        $calculator = app(CalculateCodDeposit::class);
        $none = $calculator->calculate('500.0000', CodDepositType::None, '0.0000');
        $percentage = $calculator->calculate('100.0000', CodDepositType::Percentage, '20.0000');
        $fixed = $calculator->calculate('500.0000', CodDepositType::FixedAmount, '30.0000');

        $this->assertSame(['0.0000', '500.0000'], [$none['amount_due_now'], $none['amount_due_later']]);
        $this->assertSame(['20.0000', '80.0000'], [$percentage['amount_due_now'], $percentage['amount_due_later']]);
        $this->assertSame(['30.0000', '470.0000'], [$fixed['amount_due_now'], $fixed['amount_due_later']]);
    }

    public function test_prepaid_and_cod_plans_are_snapshots_and_deposit_cannot_exceed_order(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $settings = $this->enablePayments($tenant, $store, CodDepositType::Percentage, '20.0000');
        $action = app(ConfigureOrderPaymentTerms::class);
        $prepaid = $action->handle($tenant, $store, $order, PaymentTerms::Prepaid);
        $this->assertSame($order->grand_total, $prepaid->amount_due_now);
        $this->assertSame('0.0000', $prepaid->amount_due_later);
        $cod = $action->handle($tenant, $store, $order, PaymentTerms::Cod);
        $snapshotDueNow = $cod->amount_due_now;
        $settings->update(['cod_deposit_type' => CodDepositType::FixedAmount, 'cod_deposit_value' => '1.0000']);
        $this->assertSame($snapshotDueNow, $cod->fresh()->amount_due_now);

        $settings->update(['cod_deposit_value' => '999999.0000']);
        $this->expectException(ValidationException::class);
        $action->handle($tenant, $store, $order, PaymentTerms::Cod);
    }

    public function test_terms_cannot_change_after_first_capture(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $payment = $this->manualPayment($tenant, $store, $order, '5.0000');
        app(CaptureManualPayment::class)->handle($tenant, $store, $payment, '5.0000', 'terms-lock');
        $this->assertSame(OrderPaymentStatus::PartiallyPaid, $order->fresh()->payment_status);

        $this->expectException(ValidationException::class);
        app(ConfigureOrderPaymentTerms::class)->handle($tenant, $store, $order, PaymentTerms::Cod);
    }
}
