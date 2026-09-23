<?php

namespace App\Modules\Access\Domain\Models;

use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'key', 'is_system'])]
class Role extends Model
{
    use HasUuids;

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeSystem(Builder $query, SystemRole|string|null $role = null): Builder
    {
        $query->whereNull('tenant_id')->where('is_system', true);

        return $role === null
            ? $query
            : $query->where('key', $role instanceof SystemRole ? $role->value : $role);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /** @return HasMany<TenantMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
