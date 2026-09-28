<?php

namespace App\Modules\Cart\Application;

final readonly class CartSummary
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public string $id,
        public ?string $currency,
        public int $itemCount,
        public string $subtotalBeforeDiscounts,
        public string $discountTotal,
        public string $subtotal,
        public string $taxableAmount,
        public string $exemptAmount,
        public string $unaffectedAmount,
        public string $taxTotal,
        public bool $valid,
        public array $items,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'item_count' => $this->itemCount,
            'subtotal_before_discounts' => $this->subtotalBeforeDiscounts,
            'discount_total' => $this->discountTotal,
            'subtotal' => $this->subtotal,
            'taxable_amount' => $this->taxableAmount,
            'exempt_amount' => $this->exemptAmount,
            'unaffected_amount' => $this->unaffectedAmount,
            'tax_total' => $this->taxTotal,
            'valid' => $this->valid,
            'items' => $this->items,
        ];
    }
}
