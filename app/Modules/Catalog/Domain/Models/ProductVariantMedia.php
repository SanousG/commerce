<?php

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'product_variant_id', 'media_asset_id', 'position'])]
class ProductVariantMedia extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function mediaAsset(): BelongsTo { return $this->belongsTo(MediaAsset::class); }

    protected function casts(): array { return ['position' => 'integer']; }
}
