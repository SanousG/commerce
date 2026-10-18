<?php

namespace App\Modules\Stores\Domain\Models;

use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'store_id', 'display_name', 'short_description', 'contact_email', 'contact_phone',
    'whatsapp_phone', 'address_line_1', 'address_line_2', 'district', 'province', 'department',
    'country_code', 'postal_code', 'social_links', 'logo_media_asset_id', 'favicon_media_asset_id',
    'primary_color', 'secondary_color',
])]
class StoreSettings extends Model
{
    use HasUuids;

    public const DEFAULT_SOCIAL_LINKS = [
        'facebook' => null,
        'instagram' => null,
        'tiktok' => null,
        'youtube' => null,
        'whatsapp' => null,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_asset_id');
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_asset_id');
    }

    protected function casts(): array
    {
        return ['social_links' => 'array'];
    }
}
