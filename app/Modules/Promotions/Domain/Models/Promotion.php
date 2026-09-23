<?php

namespace App\Modules\Promotions\Domain\Models;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Promotions\Domain\Enums\PromotionDiscountType;
use App\Modules\Promotions\Domain\Enums\PromotionScope;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name', 'internal_code', 'description', 'status', 'discount_type', 'discount_value', 'currency', 'starts_at', 'ends_at', 'priority', 'scope'])]
class Promotion extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products')->withPivot('tenant_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_categories')->withPivot('tenant_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'promotion_variants')->withPivot('tenant_id');
    }

    public function isEligibleAt(CarbonInterface $at): bool
    {
        return $this->status === PromotionStatus::Active
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gt($at));
    }

    protected function casts(): array
    {
        return [
            'status' => PromotionStatus::class,
            'discount_type' => PromotionDiscountType::class,
            'discount_value' => 'decimal:4',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'priority' => 'integer',
            'scope' => PromotionScope::class,
        ];
    }
}
