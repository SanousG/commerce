<?php

namespace App\Modules\Stores\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateStore
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array{name: string, status: string, currency: string, timezone: string, locale: string} $attributes */
    public function handle(Tenant $tenant, string $storeId, array $attributes, ?User $actor = null): Store
    {
        return DB::transaction(function () use ($tenant, $storeId, $attributes, $actor): Store {
            $store = $tenant->stores()->lockForUpdate()->findOrFail($storeId);
            $store->fill($attributes);
            $dirty = $store->getDirty();
            unset($dirty['updated_at']);

            if ($dirty === []) {
                return $store;
            }

            $changes = [];
            foreach (array_keys($dirty) as $field) {
                $changes[$field] = [
                    'from' => $store->getRawOriginal($field),
                    'to' => $store->getAttribute($field),
                ];
            }

            $store->save();
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::StoreUpdated,
                AuditSubjectType::Store,
                $store->getKey(),
                ['changes' => $changes],
                store: $store,
                actor: $actor,
                subjectLabel: $store->name,
            );

            return $store;
        });
    }
}
