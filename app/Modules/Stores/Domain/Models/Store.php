<?php

namespace App\Modules\Stores\Domain\Models;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'slug', 'status', 'currency', 'timezone', 'locale', 'default_price_list_id'])]
class Store extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'default_price_list_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(StoreListing::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return array<string, class-string|string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
        ];
    }
}
