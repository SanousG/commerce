<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Media\Domain\Enums\MediaDerivativeVariant;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Models\MediaDerivative;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Storefront\Application\PublicCatalog;
use App\Modules\Storefront\Application\PublicStoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicMediaController extends Controller
{
    public function __invoke(
        string $publicHandle,
        string $checksum,
        string $variant,
        PublicStoreContext $context,
        PublicCatalog $catalog,
    ): StreamedResponse {
        $store = $context->store();
        $tenant = $context->tenant();
        abort_unless($store !== null && $tenant !== null, 404);
        $derivativeVariant = match ($variant) {
            'thumbnail' => MediaDerivativeVariant::Thumbnail,
            'medium' => MediaDerivativeVariant::Medium,
            default => abort(404),
        };

        $derivative = MediaDerivative::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('checksum', $checksum)
            ->where('variant', $derivativeVariant)
            ->whereHas('mediaAsset', fn ($query) => $query->where('status', MediaStatus::Ready))
            ->with('mediaAsset')
            ->firstOrFail();

        abort_unless($this->isAuthorized($derivative, $context, $catalog), 404);
        $stream = Storage::disk($derivative->disk)->readStream($derivative->storage_key);
        if (! is_resource($stream)) {
            throw new RuntimeException('The public media derivative is unavailable.');
        }

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $derivative->mime_type,
            'Content-Length' => (string) $derivative->size_bytes,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function isAuthorized(MediaDerivative $derivative, PublicStoreContext $context, PublicCatalog $catalog): bool
    {
        $store = $context->store();
        $tenant = $context->tenant();
        if ($store === null || $tenant === null) {
            return false;
        }
        $assetId = $derivative->media_asset_id;
        $settings = $store->settings;
        if ($settings !== null && in_array($assetId, [$settings->logo_media_asset_id, $settings->favicon_media_asset_id], true)) {
            return true;
        }

        $eligibleListings = $catalog->baseQuery($context);
        if (DB::table('product_media')->where('tenant_id', $tenant->getKey())
            ->where('media_asset_id', $assetId)
            ->whereIn('product_id', (clone $eligibleListings)->select('product_id'))->exists()) {
            return true;
        }

        $fallbackListId = $store->default_price_list_id
            ?? $tenant->priceLists()->where('is_default', true)->where('status', PriceListStatus::Active)->value('id');

        return is_string($fallbackListId) && DB::table('product_variant_media as pvm')
            ->join('product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'pvm.product_variant_id')->on('pv.tenant_id', '=', 'pvm.tenant_id');
            })
            ->join('store_listing_variants as slv', function ($join) {
                $join->on('slv.product_variant_id', '=', 'pv.id')->on('slv.tenant_id', '=', 'pv.tenant_id');
            })
            ->join('store_listings as sl', function ($join) {
                $join->on('sl.id', '=', 'slv.store_listing_id')->on('sl.tenant_id', '=', 'slv.tenant_id');
            })
            ->join('variant_prices as vp', function ($join) {
                $join->on('vp.product_variant_id', '=', 'pv.id')->on('vp.tenant_id', '=', 'pv.tenant_id');
            })
            ->join('price_lists as pl', function ($join) {
                $join->on('pl.id', '=', 'vp.price_list_id')->on('pl.tenant_id', '=', 'vp.tenant_id');
            })
            ->where('pvm.tenant_id', $tenant->getKey())
            ->where('pvm.media_asset_id', $assetId)
            ->where('sl.store_id', $store->getKey())
            ->whereIn('sl.id', (clone $eligibleListings)->select('id'))
            ->where('slv.is_enabled', true)
            ->where('pv.status', ProductVariantStatus::Active)
            ->where('pl.status', PriceListStatus::Active)
            ->whereRaw('vp.price_list_id = COALESCE(sl.price_list_id, ?)', [$fallbackListId])
            ->exists();
    }
}
