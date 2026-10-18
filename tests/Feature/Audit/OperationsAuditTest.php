<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Costing\Application\InitializeInventoryCost;
use App\Modules\Fulfillment\Application\CompleteFulfillment;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use Tests\Feature\Fulfillment\FulfillmentFeatureTestCase;
use Tests\Feature\Inventory\InventoryFeatureTestCase;

class OperationsAuditTest extends InventoryFeatureTestCase
{
    public function test_cost_initialization_records_the_valuation_snapshot(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $tenant->inventoryLocations()->where('is_default', true)->sole();
        $variant = $this->createVariant($tenant, 'AUDIT-COST');
        $this->stock($tenant, $location, $variant, 3, $actor);

        $movement = app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '7.5', 'pen', $actor);

        $audit = AuditEntry::query()->where('action', AuditAction::InventoryCostInitialized)->sole();
        $this->assertSame($movement->getKey(), $audit->subject_id);
        $this->assertEquals([
            'location_code' => $location->code,
            'sku' => 'AUDIT-COST',
            'quantity' => 3,
            'unit_cost' => '7.500000',
            'value' => '22.5000',
            'currency' => 'PEN',
        ], $audit->metadata);
    }
}

class FulfillmentPaymentAuditTest extends FulfillmentFeatureTestCase
{
    public function test_payment_capture_retry_records_one_transaction_and_one_audit_without_inventory_effect(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 2);
        $this->enablePayments($tenant, $store);
        $payment = $this->manualPayment($tenant, $store, $order, $order->grand_total, $actor);
        $before = [$level->fresh()->on_hand, $level->fresh()->reserved];
        $action = app(CaptureManualPayment::class);

        $first = $action->handle($tenant, $store, $payment, $order->grand_total, 'audit-capture', 'BANK-SECRET-REF', $actor);
        $retry = $action->handle($tenant, $store, $payment->fresh(), $order->grand_total, 'audit-capture', 'BANK-SECRET-REF', $actor);

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertSame(1, PaymentTransaction::query()->where('payment_id', $payment->getKey())->count());
        $audit = AuditEntry::query()->where('action', AuditAction::PaymentCaptured)->sole();
        $this->assertEquals([
            'transaction_id' => $first->getKey(),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
        ], $audit->metadata);
        $this->assertStringNotContainsString('BANK-SECRET-REF', $audit->toJson());
        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
    }

    public function test_fulfillment_double_complete_records_one_audit_and_one_inventory_commit(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 2);
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $fulfillment = $this->fulfillment($tenant, $order, 2, $reservation, actor: $actor);
        $fulfillment = app(StartFulfillment::class)->handle($tenant, $fulfillment, $actor);

        $completed = app(CompleteFulfillment::class)->handle($tenant, $fulfillment, $actor);
        $retry = app(CompleteFulfillment::class)->handle($tenant, $completed->fresh(), $actor);

        $this->assertSame($completed->getKey(), $retry->getKey());
        $this->assertSame(1, AuditEntry::query()->where('action', AuditAction::FulfillmentCompleted)
            ->where('subject_id', $completed->getKey())->count());
        $audit = AuditEntry::query()->where('action', AuditAction::FulfillmentCompleted)
            ->where('subject_id', $completed->getKey())->sole();
        $this->assertSame(2, $audit->metadata['quantity']);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovementType::ReservationCommit)
            ->where('correlation_id', $completed->getKey())->count());
        $this->assertSame(2, $reservation->fresh()->committed_quantity);
    }
}
