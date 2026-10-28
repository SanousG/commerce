<?php

namespace App\Modules\Stores\Domain\Models;

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'store_id', 'public_handle', 'status', 'published_at'])]
class StorefrontPublication extends Model
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

    protected function casts(): array
    {
        return [
            'status' => StorefrontPublicationStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
