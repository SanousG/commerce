<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat\Catalogs;

use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use InvalidArgumentException;

final class SunatCatalog
{
    public const OPERATION_DOMESTIC_SALE = '0101';

    public const UNIT = 'NIU';

    public static function documentType(FiscalDocumentType $type): string
    {
        return match ($type) {
            FiscalDocumentType::Invoice => '01',
            FiscalDocumentType::Receipt => '03',
            default => throw new InvalidArgumentException('Unsupported SUNAT document type.'),
        };
    }

    public static function identityType(?string $type): string
    {
        return match ($type) {
            'RUC' => '6',
            'DNI' => '1',
            null => '-',
            default => throw new InvalidArgumentException('Unsupported SUNAT identity document type.'),
        };
    }

    /** @return array{affectation: string, tax_id: string, name: string, type: string} */
    public static function tax(TaxCategoryCode $category): array
    {
        return match ($category) {
            TaxCategoryCode::Standard => ['affectation' => '10', 'tax_id' => '1000', 'name' => 'IGV', 'type' => 'VAT'],
            TaxCategoryCode::Exempt => ['affectation' => '20', 'tax_id' => '9997', 'name' => 'EXO', 'type' => 'VAT'],
            TaxCategoryCode::Unaffected => ['affectation' => '30', 'tax_id' => '9998', 'name' => 'INA', 'type' => 'FRE'],
        };
    }
}
