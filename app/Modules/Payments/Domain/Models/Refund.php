<?php

namespace App\Modules\Payments\Domain\Models;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'payment_id', 'return_id', 'status', 'amount', 'currency',
    'reason', 'idempotency_key', 'external_reference', 'created_by_user_id', 'completed_by_user_id', 'completed_at',
])]
class Refund extends Model
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'decimal:4',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
