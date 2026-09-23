<?php

namespace App\Modules\Orders\Application;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(private readonly OrderInventoryReservations $reservations) {}

    public function handle(Tenant $tenant, Order $order, string $reason, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($tenant, $order, $reason, $actor): Order {
            $locked = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status === OrderStatus::Cancelled) {
                return $locked;
            }
            if ($locked->status !== OrderStatus::Confirmed
                || $locked->fulfillment_status !== OrderFulfillmentStatus::Unfulfilled) {
                throw ValidationException::withMessages(['order' => 'This order cannot be cancelled.']);
            }
            $this->reservations->release($tenant, $locked);
            $locked->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => OrderStatus::Confirmed,
                'to_status' => OrderStatus::Cancelled, 'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
            ]);

            return $locked->fresh(['history']);
        });
    }
}
