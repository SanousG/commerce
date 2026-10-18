<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Notifications\Application\OperationalNotificationPublisher;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReturn
{
    public function __construct(
        private readonly ReturnEligibility $eligibility,
        private readonly OperationalNotificationPublisher $notifications,
    ) {}

    /** @param list<array{order_item_id: string, quantity: int, reason?: string|null}> $items */
    public function handle(
        Tenant $tenant,
        Store $store,
        Order $order,
        array $items,
        ?string $reason = null,
        ?string $customerNote = null,
        ?string $internalNote = null,
        ?User $actor = null,
    ): ReturnRequest {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'A return requires at least one item.']);
        }

        return DB::transaction(function () use ($tenant, $store, $order, $items, $reason, $customerNote, $internalNote, $actor): ReturnRequest {
            $lockedOrder = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($order->getKey());
            $ids = collect($items)->pluck('order_item_id');
            if ($ids->unique()->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'Each order item may appear only once.']);
            }
            $orderItems = OrderItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $lockedOrder->getKey())
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($orderItems->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'A return item is outside this order, store, or tenant.']);
            }

            foreach ($items as $input) {
                $quantity = $input['quantity'];
                $item = $orderItems[$input['order_item_id']];
                if ($quantity <= 0 || $quantity > 9999 || $quantity > $this->eligibility->returnableQuantity($tenant, $item)) {
                    throw ValidationException::withMessages(['quantity' => 'The quantity exceeds the fulfilled returnable quantity.']);
                }
            }

            $return = ReturnRequest::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
                'order_id' => $lockedOrder->getKey(), 'status' => ReturnStatus::Requested,
                'reason' => $reason, 'customer_note' => $customerNote, 'internal_note' => $internalNote,
                'created_by_user_id' => $actor?->getKey(),
            ]);
            foreach ($items as $input) {
                $item = $orderItems[$input['order_item_id']];
                $return->items()->create([
                    'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $lockedOrder->getKey(),
                    'order_item_id' => $item->getKey(), 'product_variant_id' => $item->product_variant_id,
                    'requested_quantity' => $input['quantity'], 'reason' => $input['reason'] ?? $reason,
                    'received_quantity' => 0,
                    'accepted_quantity' => 0, 'restocked_quantity' => 0,
                ]);
            }
            $return->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => null,
                'to_status' => ReturnStatus::Requested, 'actor_user_id' => $actor?->getKey(),
            ]);
            $this->notifications->event(
                $tenant->getKey(), $store->getKey(), [PermissionKey::ReturnsManage],
                'return-requested:'.$return->getKey(), NotificationType::ReturnRequested, NotificationSeverity::Warning,
                'Return requires review', 'A return request is waiting for review.',
                '/admin/returns/'.$return->getKey(), ['return_id' => $return->getKey()],
                AuditSubjectType::ReturnRequest->value, (string) $return->getKey(),
            );

            return $return->load(['items', 'history']);
        });
    }
}
