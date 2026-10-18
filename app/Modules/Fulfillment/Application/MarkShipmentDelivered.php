<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkShipmentDelivered
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, Shipment $shipment, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($tenant, $shipment, $actor): Shipment {
            $locked = Shipment::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($shipment->getKey());
            if ($locked->status === ShipmentStatus::Delivered) {
                return $locked;
            }
            if ($locked->status !== ShipmentStatus::Shipped) {
                throw ValidationException::withMessages(['shipment' => 'Only a shipped shipment can be delivered.']);
            }
            $locked->update(['status' => ShipmentStatus::Delivered, 'delivered_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => ShipmentStatus::Shipped,
                'to_status' => ShipmentStatus::Delivered, 'actor_user_id' => $actor?->getKey(),
            ]);
            $this->audit->record(
                tenant: $tenant, actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::ShipmentDelivered, subjectType: AuditSubjectType::Shipment,
                subjectId: $locked->getKey(),
                metadata: ['tracking_number' => $locked->tracking_number, 'carrier' => $locked->carrier_name],
                store: $locked->store()->firstOrFail(), actor: $actor,
                subjectLabel: $locked->tracking_number ?? $locked->getKey(),
            );

            return $locked->fresh('history');
        });
    }
}
