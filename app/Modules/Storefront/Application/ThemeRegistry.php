<?php

namespace App\Modules\Storefront\Application;

final class ThemeRegistry
{
    public function resolve(?string $requested = null): string
    {
        $themes = config('storefront-themes.available', []);
        $fallback = (string) config('storefront-themes.default', 'essential');

        if (is_string($requested) && ($themes[$requested]['status'] ?? null) === 'available') {
            return $requested;
        }

        return ($themes[$fallback]['status'] ?? null) === 'available' ? $fallback : 'essential';
    }

    /** @return array<string, array{status: string, type: string}> */
    public function available(): array
    {
        return config('storefront-themes.available', []);
    }
}
