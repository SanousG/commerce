<?php

namespace App\Modules\Stores\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateStoreSettings
{
    private const STORE_FIELDS = ['currency', 'timezone', 'locale'];

    private const SETTINGS_FIELDS = [
        'display_name', 'short_description', 'contact_email', 'contact_phone', 'whatsapp_phone',
        'address_line_1', 'address_line_2', 'district', 'province', 'department', 'country_code',
        'postal_code', 'social_links', 'logo_media_asset_id', 'favicon_media_asset_id',
        'primary_color', 'secondary_color',
        'pickup_enabled', 'pickup_inventory_location_id', 'pickup_instructions',
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, string $storeId, array $attributes, User $actor): Store
    {
        return DB::transaction(function () use ($tenant, $storeId, $attributes, $actor): Store {
            $store = Store::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($storeId);
            $settings = StoreSettings::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureCurrent($store, (string) $attributes['store_updated_at']);
            $this->ensureCurrent($settings, (string) $attributes['settings_updated_at']);
            $this->lockBrandingAssets($tenant, Arr::only($attributes, ['logo_media_asset_id', 'favicon_media_asset_id']));
            $this->lockPickupLocation($tenant, $attributes['pickup_inventory_location_id']);
            $attributes['social_links'] = array_merge(StoreSettings::DEFAULT_SOCIAL_LINKS, $attributes['social_links']);

            $store->fill(Arr::only($attributes, self::STORE_FIELDS));
            $settings->fill(Arr::only($attributes, self::SETTINGS_FIELDS));
            $changes = [
                'store' => $this->changes($store),
                'settings' => $this->changes($settings),
            ];

            if ($changes['store'] !== []) {
                $store->save();
            }
            if ($changes['settings'] !== []) {
                $settings->save();
            }

            if ($changes['store'] !== [] || $changes['settings'] !== []) {
                $this->audit->record(
                    $tenant,
                    AuditActorType::User,
                    AuditAction::StoreUpdated,
                    AuditSubjectType::Store,
                    $store->getKey(),
                    ['changes' => $changes],
                    store: $store,
                    actor: $actor,
                    subjectLabel: $settings->display_name ?: $store->name,
                );
            }

            return $store->refresh()->load(['settings.logo', 'settings.favicon']);
        });
    }

    private function ensureCurrent(Store|StoreSettings $model, string $expected): void
    {
        if (! $model->updated_at->equalTo(CarbonImmutable::parse($expected))) {
            throw new ConflictHttpException('These store settings changed in another session. Reload before saving.');
        }
    }

    /** @param array<string, mixed> $ids */
    private function lockBrandingAssets(Tenant $tenant, array $ids): void
    {
        $ids = collect($ids)->filter()->unique()->sort()->values();
        foreach ($ids as $id) {
            MediaAsset::query()->where('tenant_id', $tenant->getKey())
                ->where('type', MediaType::Image)->where('status', MediaStatus::Ready)
                ->lockForUpdate()->findOrFail($id);
        }
    }

    private function lockPickupLocation(Tenant $tenant, mixed $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        InventoryLocation::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', InventoryLocationStatus::Active)
            ->lockForUpdate()
            ->findOrFail($locationId);
    }

    /** @return array<string, array{from: mixed, to: mixed}> */
    private function changes(Store|StoreSettings $model): array
    {
        $changes = [];
        foreach (array_keys(Arr::except($model->getDirty(), ['updated_at'])) as $field) {
            $changes[$field] = ['from' => $model->getRawOriginal($field), 'to' => $model->getAttribute($field)];
        }

        return $changes;
    }
}
