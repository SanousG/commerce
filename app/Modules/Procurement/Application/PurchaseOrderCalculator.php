<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class PurchaseOrderCalculator
{
    public function handle(PurchaseOrder $order, string|int|float|null $discountTotal = null, string|int|float|null $shippingTotal = null, string|int|float|null $taxTotal = null): PurchaseOrder
    {
        $subtotal = $order->items()->get(['line_total'])->reduce(
            fn (BigDecimal $sum, $item): BigDecimal => $sum->plus($item->line_total),
            BigDecimal::zero()->toScale(4),
        )->toScale(4);
        $discount = BigDecimal::of(ProcurementValues::amount($discountTotal ?? $order->discount_total, 'discount_total'));
        $shipping = BigDecimal::of(ProcurementValues::amount($shippingTotal ?? $order->shipping_total, 'shipping_total'));
        $tax = BigDecimal::of(ProcurementValues::amount($taxTotal ?? $order->tax_total, 'tax_total'));
        if ($discount->isGreaterThan($subtotal)) {
            throw ValidationException::withMessages(['discount_total' => 'The discount cannot exceed the subtotal.']);
        }
        $grandTotal = $subtotal->minus($discount)->plus($shipping)->plus($tax)->toScale(4);
        $order->update([
            'subtotal' => (string) $subtotal, 'discount_total' => (string) $discount,
            'shipping_total' => (string) $shipping, 'tax_total' => (string) $tax, 'grand_total' => (string) $grandTotal,
        ]);

        return $order->refresh();
    }
}
