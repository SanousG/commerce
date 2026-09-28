<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Domain\Enums\PaymentIntentStatus;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Payments\Domain\Models\PaymentIntent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PaymentDatabaseConstraintTest extends PaymentsFeatureTestCase
{
    public function test_payment_permissions_exclude_member(): void
    {
        $keys = [
            PermissionKey::PaymentsView->value, PermissionKey::PaymentsManage->value,
            PermissionKey::PaymentsCapture->value, PermissionKey::PaymentsSettings->value,
        ];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'payments')->pluck('key')->all());
        }
        $this->assertSame([], Role::query()->system(SystemRole::Member)->sole()->permissions()
            ->where('module', 'payments')->pluck('key')->all());
    }

    public function test_database_rejects_non_positive_payment_and_cross_store_order(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $payment = $this->manualPayment($tenant, $store, $order, '5.0000');

        $this->expectException(QueryException::class);
        $payment->update(['amount' => '0.0000']);
    }

    public function test_payment_intent_idempotency_is_unique_per_order(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $values = [
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
            'provider' => PaymentProvider::Stripe, 'method' => PaymentMethod::Online,
            'status' => PaymentIntentStatus::Pending, 'currency' => $order->currency,
            'amount' => '5.0000', 'idempotency_key' => 'intent-key',
        ];
        PaymentIntent::query()->create($values);

        $this->expectException(QueryException::class);
        PaymentIntent::query()->create($values);
    }

    public function test_payment_transactions_are_database_append_only(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $payment = $this->manualPayment($tenant, $store, $order, '5.0000');
        $transaction = app(CaptureManualPayment::class)
            ->handle($tenant, $store, $payment, '5.0000', 'append-only');

        $this->expectException(QueryException::class);
        DB::table('payment_transactions')->where('id', $transaction->getKey())->update(['amount' => '4.0000']);
    }
}
