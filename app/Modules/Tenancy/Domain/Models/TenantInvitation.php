<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Models\User;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'email', 'role_id', 'token_hash', 'status', 'invited_by_user_id', 'expires_at', 'accepted_at'])]
class TenantInvitation extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => strtolower(trim($value)));
    }

    protected function casts(): array
    {
        return [
            'status' => TenantInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
