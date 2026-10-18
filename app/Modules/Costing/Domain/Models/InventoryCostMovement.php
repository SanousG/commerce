<?php

namespace App\Modules\Costing\Domain\Models;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tenant_id', 'inventory_location_id', 'product_variant_id', 'inventory_movement_id', 'movement_type',
    'quantity', 'unit_cost', 'value_delta', 'average_cost_before', 'average_cost_after', 'currency',
    'reference_type', 'reference_id', 'correlation_id', 'actor_user_id', 'occurred_at',
])]
class InventoryCostMovement extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory cost movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Inventory cost movements are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryCostMovementType::class,
            'quantity' => 'integer',
            'unit_cost' => 'decimal:6',
            'value_delta' => 'decimal:4',
            'average_cost_before' => 'decimal:6',
            'average_cost_after' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
