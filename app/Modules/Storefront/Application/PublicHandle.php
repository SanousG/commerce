<?php

namespace App\Modules\Storefront\Application;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class PublicHandle
{
    public const MAX_LENGTH = 63;

    public function normalize(string $value): string
    {
        $handle = Str::slug(trim($value));

        if ($handle === '' || strlen($handle) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('The public handle must contain between 1 and 63 URL-safe characters.');
        }

        if (in_array($handle, config('storefront.reserved_handles', []), true)) {
            throw new InvalidArgumentException('The public handle is reserved.');
        }

        return $handle;
    }
}
