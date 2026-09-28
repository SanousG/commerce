<?php

namespace App\Modules\Procurement\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'supplier_id', 'product_variant_id', 'supplier_sku', 'status', 'unit_cost', 'currency', 'minimum_order_quantity', 'order_multiple', 'lead_time_days', 'is_preferred', 'notes'])]
class SupplierProduct extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SupplierProductStatus::class,
            'unit_cost' => 'decimal:4',
            'minimum_order_quantity' => 'integer',
            'order_multiple' => 'integer',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }
}
