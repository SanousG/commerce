<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Models\User;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Inventory\Domain\Models\InventoryTransfer;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Listings\Domain\Models\StoreListingVariant;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'status'])]
class Tenant extends Model
{
    use HasUuids;

    /**
     * @return HasMany<TenantMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<PriceList, $this> */
    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    /** @return HasMany<Promotion, $this> */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function taxCategories(): HasMany
    {
        return $this->hasMany(TaxCategory::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function fiscalIssuerProfile(): HasOne
    {
        return $this->hasOne(FiscalIssuerProfile::class);
    }

    public function fiscalSeries(): HasMany
    {
        return $this->hasMany(FiscalSeries::class);
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function storeListings(): HasMany
    {
        return $this->hasMany(StoreListing::class);
    }

    public function storeListingVariants(): HasMany
    {
        return $this->hasMany(StoreListingVariant::class);
    }

    /** @return HasOne<PriceList, $this> */
    public function defaultPriceList(): HasOne
    {
        return $this->hasOne(PriceList::class)->where('is_default', true);
    }

    /** @return HasMany<InventoryLocation, $this> */
    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    /** @return HasOne<InventoryLocation, $this> */
    public function defaultInventoryLocation(): HasOne
    {
        return $this->hasOne(InventoryLocation::class)->where('is_default', true);
    }

    /** @return HasMany<InventoryLevel, $this> */
    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return HasMany<InventoryTransfer, $this> */
    public function inventoryTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class);
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_members')
            ->withPivot(['id', 'role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return array<string, class-string|string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }
}
