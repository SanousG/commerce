<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Analytics\Application\ReturnsDashboardMetrics;
use Tests\Feature\Returns\ReturnsFeatureTestCase;

class ReturnsDashboardMetricsTest extends ReturnsFeatureTestCase
{
    public function test_open_inspection_and_restock_attention_follow_real_lifecycle_and_store_scope(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$firstOrder] = $this->fulfilledOrder($tenant, $store, actor: $actor);
        [$secondOrder] = $this->fulfilledOrder($tenant, $store, actor: $actor);
        $this->requestReturn($tenant, $store, $firstOrder, 1, $actor);
        $inspecting = $this->requestReturn($tenant, $store, $secondOrder, 1, $actor);
        $inspecting = $this->receiveReturn($tenant, $store, $inspecting, 1, $actor);
        $this->inspectReturnItem($tenant, $store, $inspecting, $inspecting->items()->sole(), 1, actor: $actor);

        $otherStore = $this->createStore($tenant);
        [$foreignOrder] = $this->fulfilledOrder($tenant, $otherStore, actor: $actor);
        $this->requestReturn($tenant, $otherStore, $foreignOrder, 1, $actor);

        $metrics = app(ReturnsDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), false,
        );

        $this->assertSame(2, $metrics['open']);
        $this->assertSame(1, $metrics['awaiting_inspection']);
        $this->assertSame(1, $metrics['restock_pending']);
        $this->assertArrayNotHasKey('pending_exchanges', $metrics);
    }
}
