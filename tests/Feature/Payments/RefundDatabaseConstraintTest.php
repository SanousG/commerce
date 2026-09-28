<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RefundDatabaseConstraintTest extends RefundsFeatureTestCase
{
    public function test_refund_permissions_are_owner_and_admin_only(): void
    {
        $keys = [PermissionKey::RefundsView->value, PermissionKey::RefundsManage->value];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()
                ->permissions()->where('module', 'refunds')->pluck('key')->all());
        }
        $this->assertSame([], Role::query()->system(SystemRole::Member)->sole()
            ->permissions()->where('module', 'refunds')->pluck('key')->all());
    }

    public function test_refund_idempotency_key_is_unique_per_payment(): void
    {
        [$tenant, $store, $order, $payment] = $this->refundFixture();
        $values = [
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(), 'status' => RefundStatus::Pending,
            'amount' => '1.0000', 'currency' => $order->currency, 'reason' => 'Constraint test',
            'idempotency_key' => 'database-unique',
        ];
        Refund::query()->create($values);

        $this->expectException(QueryException::class);
        Refund::query()->create($values);
    }

    public function test_database_rejects_non_positive_refund_amount(): void
    {
        [$tenant, $store, $order, $payment] = $this->refundFixture();

        $this->expectException(QueryException::class);
        Refund::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(), 'status' => RefundStatus::Pending,
            'amount' => '0.0000', 'currency' => $order->currency, 'reason' => 'Invalid amount',
            'idempotency_key' => 'invalid-amount',
        ]);
    }

    public function test_database_rejects_invalid_refund_currency(): void
    {
        [$tenant, $store, $order, $payment] = $this->refundFixture();

        $this->expectException(QueryException::class);
        Refund::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(), 'amount' => '1.0000', 'reason' => 'Invalid coherence',
            'status' => RefundStatus::Pending, 'currency' => 'usd', 'idempotency_key' => 'invalid-currency',
        ]);
    }

    public function test_database_rejects_incoherent_refund_completion(): void
    {
        [$tenant, $store, $order, $payment] = $this->refundFixture();

        $this->expectException(QueryException::class);
        Refund::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(), 'amount' => '1.0000', 'reason' => 'Invalid coherence',
            'status' => RefundStatus::Completed, 'currency' => $order->currency,
            'idempotency_key' => 'invalid-completion', 'completed_at' => null,
        ]);
    }

    public function test_database_rejects_cross_store_refund_coherence(): void
    {
        [$tenant, , $order, $payment] = $this->refundFixture();
        $otherStore = $this->createStore($tenant);

        $this->expectException(QueryException::class);
        Refund::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $otherStore->getKey(), 'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(), 'status' => RefundStatus::Pending,
            'amount' => '1.0000', 'currency' => $order->currency, 'reason' => 'Cross-store mismatch',
            'idempotency_key' => 'cross-store',
        ]);
    }

    public function test_successful_refund_transaction_is_database_append_only(): void
    {
        [$tenant, $store, , $payment] = $this->refundFixture();
        $refund = $this->createRefund($tenant, $store, $payment, '100.0000', 'append-only-create');
        $this->completeRefund($tenant, $store, $refund, 'append-only-complete', 'APPEND-ONLY');
        $transaction = $refund->transaction()->sole();

        $this->expectException(QueryException::class);
        DB::table('payment_transactions')->where('id', $transaction->getKey())->update(['amount' => '99.0000']);
    }

    private function refundFixture(): array
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000');

        return [$tenant, $store, $order, $payment];
    }
}
