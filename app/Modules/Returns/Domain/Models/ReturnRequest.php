<?php

namespace App\Modules\Returns\Domain\Models;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'status', 'reason', 'customer_note', 'internal_note', 'rejection_reason', 'cancellation_reason',
    'created_by_user_id', 'approved_at', 'rejected_at', 'cancelled_at', 'received_at',
    'inspection_started_at', 'completed_at',
])]
class ReturnRequest extends Model
{
    use HasUuids;

    protected $table = 'returns';

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id');
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'inspection_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
