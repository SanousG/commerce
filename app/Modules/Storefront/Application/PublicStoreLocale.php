<?php

namespace App\Modules\Storefront\Application;

final class PublicStoreLocale
{
    public function resolve(string $storeLocale): string
    {
        $supported = config('localization.supported', []);
        $normalized = str_replace('_', '-', trim($storeLocale));

        foreach ($supported as $locale) {
            if (strcasecmp($locale, $normalized) === 0) {
                return $locale;
            }
        }

        $language = strtolower(strtok($normalized, '-') ?: '');
        foreach ($supported as $locale) {
            if (strtolower(strtok($locale, '-') ?: '') === $language) {
                return $locale;
            }
        }

        $fallback = (string) config('localization.fallback', 'es-PE');

        return in_array($fallback, $supported, true) ? $fallback : ($supported[0] ?? 'es-PE');
    }
}
