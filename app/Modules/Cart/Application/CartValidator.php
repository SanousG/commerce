<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;
use App\Modules\Listings\Application\EligibilityResult;

class CartValidator
{
    /** @return array<int, string> */
    public function issues(
        Cart $cart,
        CartItem $item,
        EligibilityResult $eligibility,
        ?string $priceCurrency,
        int $available,
    ): array {
        $issues = collect($eligibility->reasons)->map(fn (string $reason): string => match ($reason) {
            'LISTING_NOT_PUBLISHED', 'STORE_INACTIVE' => 'LISTING_UNAVAILABLE',
            default => $reason,
        })->unique()->values();

        if ($priceCurrency !== null && $cart->currency !== null && $priceCurrency !== $cart->currency) {
            $issues->push('CURRENCY_MISMATCH');
        }
        if ($available < $item->quantity) {
            $issues->push('INSUFFICIENT_STOCK');
        }

        return $issues->unique()->values()->all();
    }
}
