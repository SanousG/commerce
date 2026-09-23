<?php

namespace App\Modules\Customers\Domain\Models;

use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'first_name', 'last_name', 'email', 'phone', 'status', 'notes'])]
class Customer extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function displayName(): string
    {
        $name = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

        return $name !== '' ? $name : ($this->email ?? $this->phone ?? 'Customer');
    }

    public static function normalizePhone(mixed $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        return preg_replace('/[\s().-]+/', '', $phone);
    }

    public static function normalizeEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }
}
