<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;

trait InteractsWithDashboard
{
    protected function useDashboardContext(User $user, string $tenantId, string $storeId): void
    {
        $this->actingAs($user)->withSession([
            TenantContext::SESSION_KEY => $tenantId,
            StoreContext::SESSION_KEY => $storeId,
        ]);
    }

    /**
     * Rewrites an order snapshot so it lands in a deterministic window and
     * currency while keeping every money CHECK constraint satisfied.
     */
    protected function placeOrder(
        Order $order,
        string $total,
        OrderStatus $status,
        string $placedAt,
        ?string $currency = null,
    ): void {
        $order->update([
            'status' => $status,
            'currency' => $currency ?? $order->currency,
            'subtotal_before_discounts' => $total,
            'discount_total' => '0.0000',
            'subtotal' => $total,
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'taxable_amount' => $total,
            'exempt_amount' => '0.0000',
            'unaffected_amount' => '0.0000',
            'grand_total' => $total,
            'placed_at' => $placedAt,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $placedAt : null,
        ]);
    }
}
