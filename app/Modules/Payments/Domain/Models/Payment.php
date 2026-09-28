<?php

namespace App\Modules\Payments\Domain\Models;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'store_id', 'order_id', 'method', 'provider', 'status', 'currency', 'amount', 'idempotency_key',
    'external_reference', 'metadata', 'created_by_user_id',
])]
class Payment extends Model
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function intent(): HasOne
    {
        return $this->hasOne(PaymentIntent::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class, 'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class, 'amount' => 'decimal:4', 'metadata' => 'array',
        ];
    }
}
