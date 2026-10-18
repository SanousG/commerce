<?php

namespace App\Modules\Notifications\Domain\Models;

use App\Models\User;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'store_id', 'recipient_user_id', 'type', 'severity', 'title', 'message',
    'subject_type', 'subject_id', 'action_url', 'deduplication_key', 'metadata', 'read_at', 'dismissed_at', 'resolved_at',
])]
class AdminNotification extends Model
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'severity' => NotificationSeverity::class,
            'metadata' => 'array',
            'read_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
