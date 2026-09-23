<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'inventory_location_id', 'product_variant_id', 'on_hand', 'reserved'])]
class InventoryLevel extends Model
{
    use HasUuids;

    protected $appends = ['available'];

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

    public function getAvailableAttribute(): int
    {
        return $this->on_hand - $this->reserved;
    }

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'reserved' => 'integer'];
    }
}
