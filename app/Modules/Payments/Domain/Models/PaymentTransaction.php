<?php

namespace App\Modules\Payments\Domain\Models;

use App\Models\User;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tenant_id', 'payment_id', 'attempt_id', 'refund_id', 'type', 'status', 'amount', 'currency', 'external_id',
    'idempotency_key', 'occurred_at', 'metadata', 'actor_user_id',
])]
class PaymentTransaction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment transactions are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'attempt_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentTransactionType::class, 'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:4', 'occurred_at' => 'immutable_datetime', 'metadata' => 'array',
        ];
    }
}
