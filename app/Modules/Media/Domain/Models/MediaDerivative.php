<?php

namespace App\Modules\Media\Domain\Models;

use App\Modules\Media\Domain\Enums\MediaDerivativeVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'media_asset_id', 'variant', 'disk', 'storage_key', 'mime_type', 'width', 'height', 'size_bytes', 'checksum'])]
class MediaDerivative extends Model
{
    use HasUuids;

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    protected function casts(): array
    {
        return ['variant' => MediaDerivativeVariant::class, 'width' => 'integer', 'height' => 'integer', 'size_bytes' => 'integer'];
    }
}
