<?php

namespace App\Modules\Procurement\Domain\Models;

use App\Models\User;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'purchase_order_id', 'supplier_id', 'inventory_location_id', 'status', 'received_at', 'reference', 'notes', 'received_by_user_id'])]
class PurchaseReceipt extends Model
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    protected function casts(): array
    {
        return ['status' => PurchaseReceiptStatus::class, 'received_at' => 'immutable_datetime'];
    }
}
