<?php

namespace App\Modules\Stores\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Stores\Application\GetStoreSettings;
use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Stores\Application\UpdateStoreSettings;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Stores\Http\Requests\UpdateStoreSettingsRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function show(Request $request, TenantContext $tenants, StoreAccess $access, GetStoreSettings $get, string $store): Response
    {
        abort_unless($access->canView($request->user(), $tenants->get()), 403);
        $store = $get->handle($tenants->get(), $store);
        $settings = $store->settings;

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
        ]);
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
