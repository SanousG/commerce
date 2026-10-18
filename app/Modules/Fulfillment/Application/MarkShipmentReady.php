<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Notifications\Application\OperationalNotificationPublisher;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkShipmentReady
{
    public function __construct(private readonly OperationalNotificationPublisher $notifications) {}

    public function handle(Tenant $tenant, Shipment $shipment, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($tenant, $shipment, $actor): Shipment {
            $locked = Shipment::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($shipment->getKey());
            if ($locked->status === ShipmentStatus::Ready) {
                return $locked;
            }
            if ($locked->status !== ShipmentStatus::Pending) {
                throw ValidationException::withMessages(['shipment' => 'Only a pending shipment can be marked ready.']);
            }
            $locked->update(['status' => ShipmentStatus::Ready, 'ready_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => ShipmentStatus::Pending,
                'to_status' => ShipmentStatus::Ready, 'actor_user_id' => $actor?->getKey(),
            ]);
            $this->notifications->event(
                $tenant->getKey(), $locked->store_id, [PermissionKey::ShipmentsView],
                'shipment-ready:'.$locked->getKey(), NotificationType::ShipmentReady, NotificationSeverity::Info,
                'Shipment ready', 'A shipment is ready for dispatch.',
                '/admin/fulfillments/'.$locked->fulfillment_id, ['shipment_id' => $locked->getKey()],
                AuditSubjectType::Shipment->value, (string) $locked->getKey(),
            );

            return $locked->fresh('history');
        });
    }
}
