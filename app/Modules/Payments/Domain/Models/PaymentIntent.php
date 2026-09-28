<?php

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentIntentStatus;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'payment_id', 'provider', 'method', 'status', 'currency',
    'amount', 'idempotency_key', 'external_id', 'expires_at',
])]
class PaymentIntent extends Model
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

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class, 'method' => PaymentMethod::class,
            'status' => PaymentIntentStatus::class, 'amount' => 'decimal:4', 'expires_at' => 'immutable_datetime',
        ];
    }
}
