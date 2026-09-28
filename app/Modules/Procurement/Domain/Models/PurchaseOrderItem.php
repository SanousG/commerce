<?php

namespace App\Modules\Procurement\Domain\Models;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'purchase_order_id', 'supplier_product_id', 'product_id', 'variant_id', 'supplier_sku', 'product_name', 'variant_name', 'sku', 'description', 'quantity_ordered', 'unit_cost', 'currency', 'line_total'])]
class PurchaseOrderItem extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    protected function casts(): array
    {
        return ['quantity_ordered' => 'integer', 'unit_cost' => 'decimal:4', 'line_total' => 'decimal:4'];
    }
}
