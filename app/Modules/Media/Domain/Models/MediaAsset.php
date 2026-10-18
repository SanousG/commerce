<?php

namespace App\Modules\Media\Domain\Models;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductMedia;
use App\Modules\Catalog\Domain\Models\ProductVariantMedia;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'type', 'disk', 'storage_key', 'original_filename', 'mime_type', 'extension', 'size_bytes', 'width', 'height', 'title', 'alt_text', 'checksum', 'status', 'created_by_user_id'])]
class MediaAsset extends Model
{
    use HasUuids;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(MediaDerivative::class);
    }

    public function productMedia(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    public function productVariantMedia(): HasMany
    {
        return $this->hasMany(ProductVariantMedia::class);
    }

    public function storeLogoSettings(): HasMany
    {
        return $this->hasMany(StoreSettings::class, 'logo_media_asset_id');
    }

    public function storeFaviconSettings(): HasMany
    {
        return $this->hasMany(StoreSettings::class, 'favicon_media_asset_id');
    }

    protected function casts(): array
    {
        return ['type' => MediaType::class, 'status' => MediaStatus::class, 'size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
