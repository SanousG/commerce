<?php

namespace App\Modules\Procurement\Domain\Models;

use App\Models\User;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'supplier_id', 'number', 'status', 'currency', 'expected_at', 'ordered_at', 'cancelled_at', 'completed_at', 'notes', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'created_by_user_id', 'approved_by_user_id'])]
class PurchaseOrder extends Model
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

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'expected_at' => 'immutable_datetime',
            'ordered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'shipping_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
        ];
    }
}
