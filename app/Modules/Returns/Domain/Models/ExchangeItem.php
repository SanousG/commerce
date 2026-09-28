<?php

namespace App\Modules\Returns\Domain\Models;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'store_id', 'exchange_id', 'return_id', 'return_item_id', 'replacement_product_variant_id', 'quantity'])]
class ExchangeItem extends Model
{
    use HasUuids;

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'replacement_product_variant_id');
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
