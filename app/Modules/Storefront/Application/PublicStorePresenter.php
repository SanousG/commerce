<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;

final class PublicStorePresenter
{
    public function __construct(
        private readonly PublicStoreLocale $locales,
        private readonly ThemeRegistry $themes,
    ) {}

    /** @return array<string, mixed> */
    public function present(StorefrontPublication $publication): array
    {
        $store = $publication->store;
        $settings = $store->settings;
        $locale = $this->locales->resolve($store->locale);
        $primary = $this->color($settings?->primary_color, '#0F172A');
        $accent = $this->color($settings?->secondary_color, '#2563EB');

        return [
            'publicHandle' => $publication->public_handle,
            'name' => $settings?->display_name ?: $store->name,
            'description' => $settings?->short_description,
            'currency' => $store->currency,
            'timezone' => $store->timezone,
            'locale' => $locale,
            'theme' => $this->themes->resolve(),
            'branding' => [
                'primaryColor' => $primary,
                'accentColor' => $accent,
                'secondaryColor' => '#0284C7',
                'logoUrl' => $this->brandingUrl($publication, $settings?->logo),
                'faviconUrl' => $this->brandingUrl($publication, $settings?->favicon, 'THUMBNAIL'),
            ],
            'contact' => [
                'email' => $settings?->contact_email,
                'phone' => $settings?->contact_phone,
                'whatsapp' => $settings?->whatsapp_phone,
                'address' => array_values(array_filter([
                    $settings?->address_line_1,
                    $settings?->address_line_2,
                    $settings?->district,
                    $settings?->province,
                    $settings?->department,
                    $settings?->country_code,
                    $settings?->postal_code,
                ])),
                'socialLinks' => array_filter($settings?->social_links ?? []),
            ],
        ];
    }

    private function color(?string $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[0-9A-F]{6}$/i', $color) === 1
            ? strtoupper($color)
            : $fallback;
    }

    private function brandingUrl(StorefrontPublication $publication, mixed $asset, string $variant = 'MEDIUM'): ?string
    {
        if ($asset === null || $asset->status !== MediaStatus::Ready) {
            return null;
        }

        $derivative = $asset->derivatives->first(
            fn ($item) => ($item->variant->value ?? $item->variant) === $variant
        );

        return $derivative === null ? null : route('storefront.development.media', [
            'publicHandle' => $publication->public_handle,
            'checksum' => $derivative->checksum,
            'variant' => strtolower($variant),
        ]);
    }
}
