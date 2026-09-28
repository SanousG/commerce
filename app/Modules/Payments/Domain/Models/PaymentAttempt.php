<?php

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Payments\Domain\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'payment_id', 'payment_intent_id', 'provider', 'status', 'attempt_number', 'external_id',
    'failure_code', 'failure_message', 'started_at', 'finished_at',
])]
class PaymentAttempt extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'attempt_id');
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class, 'status' => PaymentAttemptStatus::class,
            'attempt_number' => 'integer', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime',
        ];
    }
}
