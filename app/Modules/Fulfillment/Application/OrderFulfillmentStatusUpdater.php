<?php

namespace App\Modules\Fulfillment\Application;

use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\FulfillmentItem;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Models\Order;

class OrderFulfillmentStatusUpdater
{
    /** Must run while the owning Order row is locked. */
    public function update(Order $order): OrderFulfillmentStatus
    {
        $fulfilled = FulfillmentItem::query()
            ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
            ->where('fulfillment_items.tenant_id', $order->tenant_id)
            ->where('fulfillment_items.order_id', $order->getKey())
            ->where('fulfillments.status', FulfillmentStatus::Fulfilled)
            ->selectRaw('fulfillment_items.order_item_id, SUM(fulfillment_items.quantity) AS quantity')
            ->groupBy('fulfillment_items.order_item_id')
            ->pluck('quantity', 'fulfillment_items.order_item_id');
        $items = $order->items()->get(['id', 'quantity']);
        $fulfilledTotal = $fulfilled->sum(fn ($quantity): int => (int) $quantity);
        $complete = $items->isNotEmpty() && $items->every(
            fn ($item): bool => (int) ($fulfilled[$item->getKey()] ?? 0) === $item->quantity,
        );
        $status = $complete
            ? OrderFulfillmentStatus::Fulfilled
            : ($fulfilledTotal > 0 ? OrderFulfillmentStatus::PartiallyFulfilled : OrderFulfillmentStatus::Unfulfilled);
        if ($order->fulfillment_status !== $status) {
            $order->update(['fulfillment_status' => $status]);
        }

        return $status;
    }
}
