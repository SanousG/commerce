<?php

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Returns\Application\ApproveReturn;
use App\Modules\Returns\Application\CreateReturn;
use App\Modules\Returns\Application\InspectReturnItem;
use App\Modules\Returns\Application\ReceiveReturn;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Tests\Feature\Fulfillment\FulfillmentFeatureTestCase;

abstract class ReturnsFeatureTestCase extends FulfillmentFeatureTestCase
{
    protected function fulfilledOrder(
        Tenant $tenant,
        Store $store,
        int $orderedQuantity = 3,
        ?int $fulfilledQuantity = null,
        ?User $actor = null,
    ): array {
        [$order, $checkout, $listing, $variant, $level] = $this->readyOrder(
            $tenant,
            $store,
            $actor,
            $orderedQuantity,
        );
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->complete(
            $tenant,
            $this->fulfillment($tenant, $order, $fulfilledQuantity ?? $orderedQuantity, actor: $actor),
            $actor,
        );

        return [$order->fresh(), $checkout, $listing, $variant, $level->fresh(), $fulfillment];
    }

    protected function requestReturn(
        Tenant $tenant,
        Store $store,
        Order $order,
        int $quantity,
        ?User $actor = null,
    ): ReturnRequest {
        return app(CreateReturn::class)->handle($tenant, $store, $order, [[
            'order_item_id' => $order->items()->sole()->getKey(),
            'quantity' => $quantity,
            'reason' => 'No longer suitable',
        ]], 'Customer return', actor: $actor);
    }

    protected function receiveReturn(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        int $quantity,
        ?User $actor = null,
    ): ReturnRequest {
        app(ApproveReturn::class)->handle($tenant, $store, $return, $actor);

        return app(ReceiveReturn::class)->handle($tenant, $store, $return->fresh(), [[
            'return_item_id' => $return->items()->sole()->getKey(),
            'quantity' => $quantity,
        ]], $actor);
    }

    protected function inspectReturnItem(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        ReturnItem $item,
        int $acceptedQuantity,
        ReturnItemCondition $condition = ReturnItemCondition::Sellable,
        ReturnItemDisposition $disposition = ReturnItemDisposition::Restock,
        ?User $actor = null,
    ): ReturnItem {
        return app(InspectReturnItem::class)->handle(
            $tenant,
            $store,
            $return,
            $item,
            $condition,
            $disposition,
            $acceptedQuantity,
            'Inspected in returns test',
            $actor,
        );
    }
}
