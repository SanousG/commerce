<?php

namespace App\Modules\Returns\Application;

use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Tenancy\Domain\Models\Tenant;

class ReturnEligibility
{
    public function fulfilledQuantity(Tenant $tenant, OrderItem $item): int
    {
        return (int) $item->fulfillmentItems()
            ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
            ->where('fulfillment_items.tenant_id', $tenant->getKey())
            ->where('fulfillments.tenant_id', $tenant->getKey())
            ->where('fulfillments.store_id', $item->store_id)
            ->where('fulfillments.order_id', $item->order_id)
            ->where('fulfillments.status', FulfillmentStatus::Fulfilled)
            ->sum('fulfillment_items.quantity');
    }

    public function acceptedQuantity(Tenant $tenant, OrderItem $item, ?string $exceptReturnItemId = null): int
    {
        return (int) ReturnItem::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('store_id', $item->store_id)
            ->where('order_item_id', $item->getKey())
            ->when($exceptReturnItemId, fn ($query) => $query->where('id', '!=', $exceptReturnItemId))
            ->sum('accepted_quantity');
    }

    public function returnableQuantity(Tenant $tenant, OrderItem $item, ?string $exceptReturnItemId = null): int
    {
        return max(0, $this->fulfilledQuantity($tenant, $item)
            - $this->acceptedQuantity($tenant, $item, $exceptReturnItemId));
    }
}
