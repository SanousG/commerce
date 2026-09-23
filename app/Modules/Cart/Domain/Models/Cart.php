<?php

namespace App\Modules\Cart\Domain\Models;

use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'store_id', 'customer_id', 'token', 'status', 'currency', 'expires_at'])]
class Cart extends Model
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'expires_at' => 'immutable_datetime',
        ];
    }
}
