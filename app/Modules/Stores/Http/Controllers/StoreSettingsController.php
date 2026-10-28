<?php

namespace App\Modules\Stores\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Storefront\Application\DevelopmentStorefrontAccess;
use App\Modules\Storefront\Application\SetPublicStorePublication;
use App\Modules\Stores\Application\GetStoreSettings;
use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Stores\Application\UpdateStoreSettings;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Stores\Http\Requests\UpdateStoreSettingsRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function show(
        Request $request,
        TenantContext $tenants,
        StoreAccess $access,
        GetStoreSettings $get,
        DevelopmentStorefrontAccess $developmentAccess,
        string $store,
    ): Response {
        abort_unless($access->canView($request->user(), $tenants->get()), 403);
        $store = $get->handle($tenants->get(), $store);
        $settings = $store->settings;
        $publication = $store->storefrontPublication;

        return Inertia::render('admin/stores/Settings', [
            'store' => [
                'id' => $store->getKey(), 'name' => $store->name, 'slug' => $store->slug,
                'currency' => $store->currency, 'timezone' => $store->timezone, 'locale' => $store->locale,
                'updated_at' => $store->updated_at->toISOString(),
            ],
            'settings' => [
                ...$settings->only([
                    'display_name', 'short_description', 'contact_email', 'contact_phone', 'whatsapp_phone',
                    'address_line_1', 'address_line_2', 'district', 'province', 'department', 'country_code',
                    'postal_code', 'social_links', 'logo_media_asset_id', 'favicon_media_asset_id',
                    'primary_color', 'secondary_color',
                ]),
                'social_links' => array_merge(StoreSettings::DEFAULT_SOCIAL_LINKS, $settings->social_links ?? []),
                'updated_at' => $settings->updated_at->toISOString(),
                'logo' => $this->media($settings->logo),
                'favicon' => $this->media($settings->favicon),
            ],
            'options' => [
                'currencies' => ['PEN', 'USD', 'EUR'],
                'countries' => [['value' => 'PE', 'label' => 'Peru']],
            ],
            'mediaLimits' => ['max_image_mb' => (int) config('media.max_image_mb')],
            'storefront' => $publication === null ? null : [
                'status' => $publication->status->value,
                'public_handle' => $publication->public_handle,
                'theme' => 'Essential',
                'public_url' => $publication->status === StorefrontPublicationStatus::Published
                    && $developmentAccess->allows($request)
                        ? route('storefront.development.home', ['publicHandle' => $publication->public_handle], false)
                        : null,
            ],
        ]);
    }

    public function publish(
        Request $request,
        TenantContext $tenants,
        StoreAccess $access,
        GetStoreSettings $get,
        SetPublicStorePublication $publication,
        string $store,
    ): RedirectResponse {
        return $this->setPublication($request, $tenants, $access, $get, $publication, $store, true);
    }

    public function unpublish(
        Request $request,
        TenantContext $tenants,
        StoreAccess $access,
        GetStoreSettings $get,
        SetPublicStorePublication $publication,
        string $store,
    ): RedirectResponse {
        return $this->setPublication($request, $tenants, $access, $get, $publication, $store, false);
    }

    public function update(
        UpdateStoreSettingsRequest $request,
        TenantContext $tenants,
        UpdateStoreSettings $update,
        string $store,
    ): RedirectResponse {
        $update->handle($tenants->get(), $store, $request->validated(), $request->user());

        return back()->with('status', 'Store settings saved.');
    }

    private function setPublication(
        Request $request,
        TenantContext $tenants,
        StoreAccess $access,
        GetStoreSettings $get,
        SetPublicStorePublication $publication,
        string $storeId,
        bool $publish,
    ): RedirectResponse {
        $tenant = $tenants->get();
        abort_unless($access->canUpdate($request->user(), $tenant), 403);
        $store = $get->handle($tenant, $storeId);

        try {
            $publication->handle((string) $tenant->getKey(), (string) $store->getKey(), $publish);
        } catch (ModelNotFoundException) {
            return back()->withErrors([
                'storefront' => $publish
                    ? __('The Storefront can only be published when its company and Store are active and prepared.')
                    : __('The Storefront could not be unpublished.'),
            ]);
        }

        return back()->with('status', $publish ? 'Storefront published.' : 'Storefront unpublished.');
    }

    /** @return array<string, mixed>|null */
    private function media(?MediaAsset $asset): ?array
    {
        if ($asset === null) {
            return null;
        }

        return [
            'id' => $asset->getKey(), 'title' => $asset->title,
            'original_filename' => $asset->original_filename, 'alt_text' => $asset->alt_text,
            'width' => $asset->width, 'height' => $asset->height,
            'thumbnail_url' => route('admin.media.thumbnail', $asset),
        ];
    }
}
