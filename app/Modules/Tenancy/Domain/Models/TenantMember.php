<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Models\User;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'role_id', 'status', 'joined_at'])]
class TenantMember extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return array<string, class-string|string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantMemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }
}
