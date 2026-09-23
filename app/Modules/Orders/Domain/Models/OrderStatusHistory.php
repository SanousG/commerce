<?php

namespace App\Modules\Orders\Domain\Models;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'order_id', 'from_status', 'to_status', 'actor_user_id', 'reason'])]
class OrderStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class];
    }
}
