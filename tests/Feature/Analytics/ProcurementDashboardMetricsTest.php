<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Analytics\Application\ProcurementDashboardMetrics;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use Tests\Feature\Procurement\ProcurementFeatureTestCase;

class ProcurementDashboardMetricsTest extends ProcurementFeatureTestCase
{
    public function test_incoming_uses_only_posted_receipts_and_is_tenant_isolated(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $order = $this->orderedOrder(
            $tenant, $supplier, $this->supplierProduct($tenant, $supplier, $variant), $actor, 100,
        );
        $receipt = $this->receipt($tenant, $order, $this->createLocation($tenant), $actor, 40);
        app(PostPurchaseReceipt::class)->handle($tenant, $receipt);

        $foreignActor = User::factory()->create();
        $foreignTenant = $this->createTenantFor($foreignActor);
        $foreignVariant = $this->createVariant($foreignTenant);
        $foreignSupplier = $this->supplier($foreignTenant);
        $this->orderedOrder(
            $foreignTenant,
            $foreignSupplier,
            $this->supplierProduct($foreignTenant, $foreignSupplier, $foreignVariant),
            $foreignActor,
            999,
        );

        $metrics = app(ProcurementDashboardMetrics::class)->build($tenant->getKey());

        $this->assertSame(1, $metrics['to_receive']);
        $this->assertSame(60, $metrics['incoming_units']);
        $this->assertSame(1, $metrics['by_status']['PARTIALLY_RECEIVED']);
        $this->assertCount(1, $metrics['purchase_orders']);
        $this->assertSame(60, $metrics['purchase_orders'][0]['remaining_quantity']);
    }
}
