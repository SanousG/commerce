<?php

namespace App\Modules\Fiscal\Domain\Models;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'order_id', 'fiscal_document_id', 'order_item_id', 'position', 'description', 'sku',
    'quantity', 'unit_price', 'base_amount', 'discount_amount', 'tax_category_code', 'tax_rate',
    'taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_amount', 'line_total',
])]
class FiscalDocumentItem extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'base_amount' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_category_code' => TaxCategoryCode::class,
            'tax_rate' => 'decimal:6',
            'taxable_amount' => 'decimal:4',
            'exempt_amount' => 'decimal:4',
            'unaffected_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }
}
