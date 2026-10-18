<?php

namespace App\Modules\Costing\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InitializeInventoryCost
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryValuationLocker $valuations,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        InventoryLocation $location,
        ProductVariant $variant,
        string $unitCost,
        string $currency,
        ?User $actor = null,
    ): InventoryCostMovement {
        return DB::transaction(function () use ($tenant, $location, $variant, $unitCost, $currency, $actor): InventoryCostMovement {
            $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($location->getKey());
            $variant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($variant->getKey());
            if ($location->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['inventory_location_id' => 'Cost can only be initialized at an active location.']);
            }
            $level = $this->levels->lock($tenant, $location->getKey(), $variant->getKey());
            $valuation = $this->valuations->lock($level);
            if ($level->on_hand <= 0) {
                throw ValidationException::withMessages(['inventory_cost' => 'Inventory cost initialization requires positive on-hand stock.']);
            }
            if ($valuation->average_unit_cost !== null) {
                throw ValidationException::withMessages(['inventory_cost' => 'Inventory cost is already initialized.']);
            }

            $cost = BigDecimal::of($unitCost)->toScale(6, RoundingMode::HalfUp);
            $currency = strtoupper($currency);
            $valuation->update([
                'average_unit_cost' => (string) $cost,
                'currency' => $currency,
                'version' => $valuation->version + 1,
            ]);

            $movement = InventoryCostMovement::create([
                'tenant_id' => $tenant->getKey(),
                'inventory_location_id' => $location->getKey(),
                'product_variant_id' => $variant->getKey(),
                'movement_type' => InventoryCostMovementType::OpeningCost,
                'quantity' => $level->on_hand,
                'unit_cost' => (string) $cost,
                'value_delta' => (string) $cost->multipliedBy($level->on_hand)->toScale(4, RoundingMode::HalfUp),
                'average_cost_before' => null,
                'average_cost_after' => (string) $cost,
                'currency' => $currency,
                'reference_type' => 'INVENTORY_VALUATION',
                'reference_id' => $valuation->getKey(),
                'correlation_id' => $valuation->getKey(),
                'actor_user_id' => $actor?->getKey(),
                'occurred_at' => now(),
            ]);
            $this->audit->record(
                tenant: $tenant, actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::InventoryCostInitialized, subjectType: AuditSubjectType::InventoryCostMovement,
                subjectId: $movement->getKey(),
                metadata: [
                    'location_code' => $location->code,
                    'sku' => $variant->sku,
                    'quantity' => $level->on_hand,
                    'unit_cost' => (string) $cost,
                    'value' => $movement->value_delta,
                    'currency' => $currency,
                ],
                actor: $actor,
                subjectLabel: $variant->sku.' @ '.$location->code,
            );

            return $movement;
        });
    }
}
