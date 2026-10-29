<?php

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'terms', 'currency', 'total_amount', 'amount_due_now',
    'amount_due_later', 'deposit_type', 'deposit_value', 'expected_method',
])]
class OrderPaymentPlan extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'terms' => PaymentTerms::class, 'deposit_type' => CodDepositType::class,
            'expected_method' => PaymentMethod::class,
            'total_amount' => 'decimal:4', 'amount_due_now' => 'decimal:4',
            'amount_due_later' => 'decimal:4', 'deposit_value' => 'decimal:4',
        ];
    }
}
