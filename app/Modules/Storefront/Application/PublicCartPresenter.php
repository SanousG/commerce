<?php

namespace App\Modules\Storefront\Application;

use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Cart\Application\CartState;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;

final class PublicCartPresenter
{
    public function __construct(
        private readonly CartCalculator $calculator,
        private readonly CartState $state,
        private readonly PublicCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function present(?Cart $cart, PublicStoreContext $context): array
    {
        if ($cart === null) {
            return $this->empty();
        }

        $summary = $this->calculator->calculate($cart);
        $cart->loadMissing([
            'items.listing.product.categories',
            'items.listing.product.media.mediaAsset.derivatives',
            'items.variant.optionValues.option',
            'items.variant.media.mediaAsset.derivatives',
        ]);
        $summaryItems = collect($summary->items)->keyBy('id');

        $items = $cart->items->map(function (CartItem $item) use ($summaryItems, $context): array {
            $line = $summaryItems->get($item->getKey(), []);
            $product = $item->listing->product;
            $variantImages = $this->catalog->media($context, $item->variant->media, $product->name);
            $productImages = $this->catalog->media($context, $product->media, $product->name);

            return [
                'key' => $this->lineKey($item),
                'productName' => $product->name,
                'productUrl' => route('storefront.development.products.show', [
                    $context->publication()?->public_handle,
                    $item->listing->storefront_slug,
                ]),
                'category' => $product->categories->first()?->name,
                'variantName' => $item->variant->name ?: $item->variant->sku,
                'options' => $item->variant->optionValues->map(fn ($value): array => [
                    'name' => $value->option->name,
                    'value' => $value->value,
                ])->values()->all(),
                'image' => $variantImages->first() ?? $productImages->first(),
                'quantity' => $item->quantity,
                'available' => (int) ($line['available'] ?? 0),
                'baseUnitPrice' => $line['base_unit_price'] ?? null,
                'finalUnitPrice' => $line['final_unit_price'] ?? null,
                'lineTotal' => $line['line_total'] ?? null,
                'issues' => array_values($line['issues'] ?? []),
            ];
        })->values()->all();

        return [
            'itemCount' => $summary->itemCount,
            'lineCount' => count($items),
            'currency' => $summary->currency,
            'subtotalBeforeDiscounts' => $summary->subtotalBeforeDiscounts,
            'discountTotal' => $summary->discountTotal,
            'subtotal' => $summary->subtotal,
            'taxTotal' => $summary->taxTotal,
            'valid' => $summary->valid,
            'maxQuantity' => $this->state->maxQuantity(),
            'items' => $items,
        ];
    }

    public function resolveItem(Cart $cart, string $lineKey): CartItem
    {
        $item = $cart->items()->get()->first(
            fn (CartItem $item): bool => hash_equals($this->lineKey($item), $lineKey)
        );
        abort_unless($item !== null, 404);

        return $item;
    }

    private function lineKey(CartItem $item): string
    {
        return hash_hmac('sha256', $item->getKey(), (string) config('app.key'));
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'itemCount' => 0,
            'lineCount' => 0,
            'currency' => null,
            'subtotalBeforeDiscounts' => '0.0000',
            'discountTotal' => '0.0000',
            'subtotal' => '0.0000',
            'taxTotal' => '0.0000',
            'valid' => true,
            'maxQuantity' => $this->state->maxQuantity(),
            'items' => [],
        ];
    }
}
