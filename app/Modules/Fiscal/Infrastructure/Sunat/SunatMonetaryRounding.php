<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Reconciles a fiscal document snapshot (numeric(19,4)) with the 2-decimal
 * amounts SUNAT accepts, without ever letting per-line rounding drift away
 * from the document totals.
 *
 * Rules:
 *  - line extension = round2(line base)
 *  - line total = round2(line total)
 *  - line tax = line total - line extension
 *  - document LineExtension/TaxExclusive = sum of line extensions
 *  - document TaxAmount = sum of line taxes
 *  - document TaxInclusive/Payable = sum of line totals
 *    and it must equal round2(grand_total), otherwise the document cannot be
 *    represented consistently and submission is refused.
 */
final readonly class SunatMonetaryRounding
{
    /** @var list<SunatLineMoney> */
    public array $lines;

    public BigDecimal $lineExtensionTotal;

    public BigDecimal $taxTotal;

    public BigDecimal $payableTotal;

    /** @var array<string, array{extension: BigDecimal, tax: BigDecimal}> keyed by tax category value */
    public array $categories;

    /** @param list<SunatLineMoney> $lines @param array<string, array{extension: BigDecimal, tax: BigDecimal}> $categories */
    private function __construct(array $lines, BigDecimal $lineExtensionTotal, BigDecimal $taxTotal, BigDecimal $payableTotal, array $categories)
    {
        $this->lines = $lines;
        $this->lineExtensionTotal = $lineExtensionTotal;
        $this->taxTotal = $taxTotal;
        $this->payableTotal = $payableTotal;
        $this->categories = $categories;
    }

    public static function for(FiscalDocument $document): self
    {
        $document->loadMissing('items');
        if ($document->items->isEmpty()) {
            throw new InvalidArgumentException('SUNAT_UBL_ITEMS_REQUIRED');
        }

        $round = static fn (string $value): BigDecimal => BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
        $lines = [];
        $lineExtensionTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $payableTotal = BigDecimal::zero();
        $categories = [];
        $exactBase = BigDecimal::zero();
        $exactTax = BigDecimal::zero();
        $exactTotal = BigDecimal::zero();

        foreach ($document->items->sortBy('position')->values() as $item) {
            $extension = $round($item->base_amount);
            $total = $round($item->line_total);
            if ($item->tax_category_code !== TaxCategoryCode::Standard && ! $total->minus($extension)->isZero()) {
                throw new InvalidArgumentException('SUNAT_UBL_UNEXPECTED_CATEGORY_TAX');
            }
            $lines[] = new SunatLineMoney(
                position: (int) $item->position,
                extension: $extension,
                tax: $total->minus($extension),
                total: $total,
                category: $item->tax_category_code,
                rate: $item->tax_rate !== null ? (string) $item->tax_rate : null,
            );
            $lineExtensionTotal = $lineExtensionTotal->plus($extension);
            $taxTotal = $taxTotal->plus($total->minus($extension));
            $payableTotal = $payableTotal->plus($total);

            $key = $item->tax_category_code->value;
            $categories[$key] = [
                'extension' => ($categories[$key]['extension'] ?? BigDecimal::zero())->plus($extension),
                'tax' => ($categories[$key]['tax'] ?? BigDecimal::zero())->plus($total->minus($extension)),
            ];

            $exactBase = $exactBase->plus(BigDecimal::of($item->base_amount));
            $exactTax = $exactTax->plus(BigDecimal::of($item->tax_amount));
            $exactTotal = $exactTotal->plus(BigDecimal::of($item->line_total));
        }

        $snapshotBase = BigDecimal::of($document->taxable_amount)
            ->plus(BigDecimal::of($document->exempt_amount))
            ->plus(BigDecimal::of($document->unaffected_amount));
        if (! $exactBase->isEqualTo($snapshotBase)
            || ! $exactTax->isEqualTo(BigDecimal::of($document->tax_amount))
            || ! $exactTotal->isEqualTo(BigDecimal::of($document->grand_total))) {
            throw new InvalidArgumentException('SUNAT_UBL_TOTALS_DO_NOT_MATCH_ITEMS');
        }

        if (! $payableTotal->isEqualTo($round($document->grand_total))) {
            throw new InvalidArgumentException('SUNAT_UBL_ROUNDING_DRIFT_NOT_RECONCILED');
        }

        return new self($lines, $lineExtensionTotal, $taxTotal, $payableTotal, $categories);
    }

    public function line(int $position): SunatLineMoney
    {
        foreach ($this->lines as $line) {
            if ($line->position === $position) {
                return $line;
            }
        }

        throw new InvalidArgumentException('SUNAT_UBL_LINE_NOT_FOUND');
    }

    public function categoryExtension(TaxCategoryCode $category): BigDecimal
    {
        return $this->categories[$category->value]['extension'] ?? BigDecimal::zero();
    }

    public function categoryTax(TaxCategoryCode $category): BigDecimal
    {
        return $this->categories[$category->value]['tax'] ?? BigDecimal::zero();
    }
}
