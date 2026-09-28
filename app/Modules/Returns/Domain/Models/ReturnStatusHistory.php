<?php

namespace App\Modules\Returns\Domain\Models;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['tenant_id', 'return_id', 'from_status', 'to_status', 'actor_user_id', 'reason'])]
class ReturnStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Return status histories are append-only.'));
        static::deleting(fn () => throw new LogicException('Return status histories are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['from_status' => ReturnStatus::class, 'to_status' => ReturnStatus::class];
    }
}
