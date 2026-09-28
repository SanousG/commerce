<?php

namespace App\Modules\Returns\Domain\Models;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'return_id', 'order_id', 'order_item_id', 'product_variant_id', 'requested_quantity', 'reason',
    'received_quantity', 'accepted_quantity', 'restocked_quantity', 'condition', 'disposition',
    'inspection_notes', 'inspected_by_user_id', 'inspected_at',
])]
class ReturnItem extends Model
{
    use HasUuids;

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function exchangeItems(): HasMany
    {
        return $this->hasMany(ExchangeItem::class);
    }

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'received_quantity' => 'integer',
            'accepted_quantity' => 'integer',
            'restocked_quantity' => 'integer',
            'condition' => ReturnItemCondition::class,
            'disposition' => ReturnItemDisposition::class,
            'inspected_at' => 'immutable_datetime',
        ];
    }
}
