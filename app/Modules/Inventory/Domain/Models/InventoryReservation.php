<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'inventory_location_id', 'product_variant_id', 'reference_type', 'reference_id', 'quantity',
    'status', 'expires_at', 'released_at', 'committed_at', 'expired_at',
])]
class InventoryReservation extends Model
{
    use HasUuids;

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

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => InventoryReservationStatus::class,
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
        ];
    }
}
