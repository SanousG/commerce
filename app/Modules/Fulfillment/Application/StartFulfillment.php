<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartFulfillment
{
    public function handle(Tenant $tenant, Fulfillment $fulfillment, ?User $actor = null): Fulfillment
    {
        return DB::transaction(function () use ($tenant, $fulfillment, $actor): Fulfillment {
            $locked = Fulfillment::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($fulfillment->getKey());
            if ($locked->status === FulfillmentStatus::Processing) {
                return $locked;
            }
            if ($locked->status !== FulfillmentStatus::Pending) {
                throw ValidationException::withMessages(['fulfillment' => 'Only a pending fulfillment can be started.']);
            }
            $locked->update(['status' => FulfillmentStatus::Processing, 'started_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => FulfillmentStatus::Pending,
                'to_status' => FulfillmentStatus::Processing, 'actor_user_id' => $actor?->getKey(),
            ]);

            return $locked->fresh(['items', 'history']);
        });
    }
}
