<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'inventory_location_id', 'product_variant_id', 'type', 'quantity_delta',
    'quantity_before', 'quantity_after', 'reference_type', 'reference_id', 'correlation_id', 'reason', 'actor_user_id',
    'occurred_at',
])]
class InventoryMovement extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
