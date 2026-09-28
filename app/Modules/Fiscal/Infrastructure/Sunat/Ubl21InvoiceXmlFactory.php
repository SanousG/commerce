<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalDocumentItem;
use App\Modules\Fiscal\Infrastructure\Sunat\Catalogs\SunatCatalog;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class Ubl21InvoiceXmlFactory
{
    private const INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const DS = 'http://www.w3.org/2000/09/xmldsig#';

    public function build(FiscalDocument $document, FiscalDocumentType $expectedType): string
    {
        if ($document->document_type !== $expectedType || ! in_array($expectedType, [FiscalDocumentType::Invoice, FiscalDocumentType::Receipt], true)) {
            throw new InvalidArgumentException('SUNAT_DOCUMENT_TYPE_MISMATCH');
        }
        if (BigDecimal::of($document->discount_total)->isGreaterThan(0)) {
            throw new InvalidArgumentException('SUNAT_UBL_DISCOUNTS_NOT_REPRESENTABLE');
        }

        $money = SunatMonetaryRounding::for($document);

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $root = $xml->createElementNS(self::INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::DS);
        $xml->appendChild($root);

        $this->text($xml, $root, self::CBC, 'cbc:UBLVersionID', '2.1');
        $customization = $this->text($xml, $root, self::CBC, 'cbc:CustomizationID', '2.0');
        $customization->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $this->text($xml, $root, self::CBC, 'cbc:ID', $document->full_number);
        $this->text($xml, $root, self::CBC, 'cbc:IssueDate', $document->issue_date->toDateString());
        $type = $this->text($xml, $root, self::CBC, 'cbc:InvoiceTypeCode', SunatCatalog::documentType($expectedType));
        $type->setAttribute('listID', SunatCatalog::OPERATION_DOMESTIC_SALE);
        $type->setAttribute('listAgencyName', 'PE:SUNAT');
        $type->setAttribute('listName', 'Tipo de Documento');
        $type->setAttribute('listURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01');
        $type->setAttribute('name', 'Tipo de Operacion');
        $type->setAttribute('listSchemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51');
        $currency = $this->text($xml, $root, self::CBC, 'cbc:DocumentCurrencyCode', $document->currency);
        $currency->setAttribute('listID', 'ISO 4217 Alpha');
        $currency->setAttribute('listName', 'Currency');
        $currency->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');

        $this->signatureReference($xml, $root, $document);
        $this->supplier($xml, $root, $document);
        $this->customer($xml, $root, $document);
        if ($expectedType === FiscalDocumentType::Invoice) {
            $payment = $this->node($xml, $root, self::CAC, 'cac:PaymentTerms');
            $this->text($xml, $payment, self::CBC, 'cbc:ID', 'FormaPago');
            $this->text($xml, $payment, self::CBC, 'cbc:PaymentMeansID', 'Contado');
        }
        $this->documentTaxes($xml, $root, $document, $money);
        $this->monetaryTotals($xml, $root, $document, $money);
        foreach ($money->lines as $line) {
            $this->line($xml, $root, $document, $line);
        }

        return $xml->saveXML() ?: throw new \RuntimeException('SUNAT_UBL_SERIALIZATION_FAILED');
    }

    private function signatureReference(DOMDocument $xml, DOMElement $root, FiscalDocument $document): void
    {
        $signature = $this->node($xml, $root, self::CAC, 'cac:Signature');
        $this->text($xml, $signature, self::CBC, 'cbc:ID', 'IDSignSP');
        $party = $this->node($xml, $signature, self::CAC, 'cac:SignatoryParty');
        $id = $this->node($xml, $party, self::CAC, 'cac:PartyIdentification');
        $this->text($xml, $id, self::CBC, 'cbc:ID', $document->issuer_tax_id);
        $name = $this->node($xml, $party, self::CAC, 'cac:PartyName');
        $this->text($xml, $name, self::CBC, 'cbc:Name', $document->issuer_legal_name);
        $attachment = $this->node($xml, $signature, self::CAC, 'cac:DigitalSignatureAttachment');
        $reference = $this->node($xml, $attachment, self::CAC, 'cac:ExternalReference');
        $this->text($xml, $reference, self::CBC, 'cbc:URI', '#SignatureSP');
    }

    private function supplier(DOMDocument $xml, DOMElement $root, FiscalDocument $document): void
    {
        $supplier = $this->node($xml, $root, self::CAC, 'cac:AccountingSupplierParty');
        $party = $this->node($xml, $supplier, self::CAC, 'cac:Party');
        $identification = $this->node($xml, $party, self::CAC, 'cac:PartyIdentification');
        $id = $this->text($xml, $identification, self::CBC, 'cbc:ID', $document->issuer_tax_id);
        $this->identityAttributes($id, '6');
        if ($document->issuer_trade_name !== null) {
            $partyName = $this->node($xml, $party, self::CAC, 'cac:PartyName');
            $this->text($xml, $partyName, self::CBC, 'cbc:Name', $document->issuer_trade_name);
        }
        $legal = $this->node($xml, $party, self::CAC, 'cac:PartyLegalEntity');
        $this->text($xml, $legal, self::CBC, 'cbc:RegistrationName', $document->issuer_legal_name);
        $address = $this->node($xml, $legal, self::CAC, 'cac:RegistrationAddress');
        if ($document->issuer_ubigeo !== null) {
            $this->text($xml, $address, self::CBC, 'cbc:ID', $document->issuer_ubigeo);
        }
        if ($document->issuer_province !== null) {
            $this->text($xml, $address, self::CBC, 'cbc:CityName', $document->issuer_province);
        }
        if ($document->issuer_postal_code !== null) {
            $this->text($xml, $address, self::CBC, 'cbc:PostalZone', $document->issuer_postal_code);
        }
        if ($document->issuer_region !== null) {
            $this->text($xml, $address, self::CBC, 'cbc:Region', $document->issuer_region);
        }
        if ($document->issuer_district !== null) {
            $this->text($xml, $address, self::CBC, 'cbc:District', $document->issuer_district);
        }
        $line = $this->node($xml, $address, self::CAC, 'cac:AddressLine');
        $this->text($xml, $line, self::CBC, 'cbc:Line', trim($document->issuer_address_line_1.' '.($document->issuer_address_line_2 ?? '')));
        $country = $this->node($xml, $address, self::CAC, 'cac:Country');
        $this->text($xml, $country, self::CBC, 'cbc:IdentificationCode', $document->issuer_country_code);
    }

    private function customer(DOMDocument $xml, DOMElement $root, FiscalDocument $document): void
    {
        $customer = $this->node($xml, $root, self::CAC, 'cac:AccountingCustomerParty');
        $party = $this->node($xml, $customer, self::CAC, 'cac:Party');
        $identification = $this->node($xml, $party, self::CAC, 'cac:PartyIdentification');
        $id = $this->text($xml, $identification, self::CBC, 'cbc:ID', $document->customer_document_number ?? '-');
        $this->identityAttributes($id, SunatCatalog::identityType($document->customer_document_type));
        if ($document->customer_address !== null && trim($document->customer_address) !== '') {
            $address = $this->node($xml, $party, self::CAC, 'cac:PostalAddress');
            $this->text($xml, $address, self::CBC, 'cbc:StreetName', trim($document->customer_address));
        }
        $legal = $this->node($xml, $party, self::CAC, 'cac:PartyLegalEntity');
        $this->text($xml, $legal, self::CBC, 'cbc:RegistrationName', $document->customer_name);
    }

    private function identityAttributes(DOMElement $id, string $schemeId): void
    {
        $id->setAttribute('schemeID', $schemeId);
        $id->setAttribute('schemeName', 'Documento de Identidad');
        $id->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $id->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06');
    }

    private function documentTaxes(DOMDocument $xml, DOMElement $root, FiscalDocument $document, SunatMonetaryRounding $money): void
    {
        $total = $this->node($xml, $root, self::CAC, 'cac:TaxTotal');
        $this->money($xml, $total, 'cbc:TaxAmount', (string) $money->taxTotal, $document->currency);
        foreach ([TaxCategoryCode::Standard, TaxCategoryCode::Exempt, TaxCategoryCode::Unaffected] as $category) {
            $base = $money->categoryExtension($category);
            if ($base->isZero()) {
                continue;
            }
            $tax = $money->categoryTax($category);
            $this->taxSubtotal($xml, $total, $category, (string) $base, (string) $tax, null, $document->currency);
        }
    }

    private function monetaryTotals(DOMDocument $xml, DOMElement $root, FiscalDocument $document, SunatMonetaryRounding $money): void
    {
        $totals = $this->node($xml, $root, self::CAC, 'cac:LegalMonetaryTotal');
        $this->money($xml, $totals, 'cbc:LineExtensionAmount', (string) $money->lineExtensionTotal, $document->currency);
        $this->money($xml, $totals, 'cbc:TaxExclusiveAmount', (string) $money->lineExtensionTotal, $document->currency);
        $this->money($xml, $totals, 'cbc:TaxInclusiveAmount', (string) $money->payableTotal, $document->currency);
        $this->money($xml, $totals, 'cbc:PayableAmount', (string) $money->payableTotal, $document->currency);
    }

    private function line(DOMDocument $xml, DOMElement $root, FiscalDocument $document, SunatLineMoney $line): void
    {
        $item = $document->items->firstWhere('position', $line->position);
        if ($item instanceof FiscalDocumentItem === false) {
            throw new InvalidArgumentException('SUNAT_UBL_LINE_NOT_FOUND');
        }
        if (BigDecimal::of($item->discount_amount)->isGreaterThan(0)) {
            throw new InvalidArgumentException('SUNAT_UBL_DISCOUNTS_NOT_REPRESENTABLE');
        }
        $node = $this->node($xml, $root, self::CAC, 'cac:InvoiceLine');
        $this->text($xml, $node, self::CBC, 'cbc:ID', (string) $line->position);
        $quantity = $this->text($xml, $node, self::CBC, 'cbc:InvoicedQuantity', $this->decimal($item->quantity, 4));
        $quantity->setAttribute('unitCode', SunatCatalog::UNIT);
        $this->money($xml, $node, 'cbc:LineExtensionAmount', (string) $line->extension, $document->currency);
        $pricing = $this->node($xml, $node, self::CAC, 'cac:PricingReference');
        $alternative = $this->node($xml, $pricing, self::CAC, 'cac:AlternativeConditionPrice');
        $grossUnit = $line->total->dividedBy($item->quantity, 10, RoundingMode::HalfUp);
        $this->money($xml, $alternative, 'cbc:PriceAmount', (string) $grossUnit, $document->currency, 10);
        $this->text($xml, $alternative, self::CBC, 'cbc:PriceTypeCode', '01');
        $taxTotal = $this->node($xml, $node, self::CAC, 'cac:TaxTotal');
        $this->money($xml, $taxTotal, 'cbc:TaxAmount', (string) $line->tax, $document->currency);
        $this->taxSubtotal($xml, $taxTotal, $line->category, (string) $line->extension, (string) $line->tax, $line->rate, $document->currency);
        $description = $this->node($xml, $node, self::CAC, 'cac:Item');
        $this->text($xml, $description, self::CBC, 'cbc:Description', $item->description);
        $price = $this->node($xml, $node, self::CAC, 'cac:Price');
        $netUnit = $line->extension->dividedBy($item->quantity, 10, RoundingMode::HalfUp);
        $this->money($xml, $price, 'cbc:PriceAmount', (string) $netUnit, $document->currency, 10);
    }

    private function taxSubtotal(DOMDocument $xml, DOMElement $parent, TaxCategoryCode $category, string $base, string $tax, ?string $rate, string $currency): void
    {
        $catalog = SunatCatalog::tax($category);
        $subtotal = $this->node($xml, $parent, self::CAC, 'cac:TaxSubtotal');
        $this->money($xml, $subtotal, 'cbc:TaxableAmount', $base, $currency);
        $this->money($xml, $subtotal, 'cbc:TaxAmount', $tax, $currency);
        $taxCategory = $this->node($xml, $subtotal, self::CAC, 'cac:TaxCategory');
        if ($rate !== null) {
            $this->text($xml, $taxCategory, self::CBC, 'cbc:Percent', $this->decimal($rate, 5));
        }
        if ($rate !== null) {
            $reason = $this->text($xml, $taxCategory, self::CBC, 'cbc:TaxExemptionReasonCode', $catalog['affectation']);
            $reason->setAttribute('listAgencyName', 'PE:SUNAT');
            $reason->setAttribute('listName', 'Afectacion del IGV');
            $reason->setAttribute('listURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07');
        }
        $scheme = $this->node($xml, $taxCategory, self::CAC, 'cac:TaxScheme');
        $id = $this->text($xml, $scheme, self::CBC, 'cbc:ID', $catalog['tax_id']);
        $id->setAttribute('schemeName', 'Codigo de tributos');
        $id->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $id->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo05');
        $this->text($xml, $scheme, self::CBC, 'cbc:Name', $catalog['name']);
        $this->text($xml, $scheme, self::CBC, 'cbc:TaxTypeCode', $catalog['type']);
    }

    private function money(DOMDocument $xml, DOMElement $parent, string $name, string $value, string $currency, int $scale = 2): DOMElement
    {
        $element = $this->text($xml, $parent, self::CBC, $name, $this->decimal($value, $scale));
        $element->setAttribute('currencyID', $currency);

        return $element;
    }

    private function decimal(string $value, int $scale): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
    }

    private function node(DOMDocument $xml, DOMElement $parent, string $namespace, string $name): DOMElement
    {
        $element = $xml->createElementNS($namespace, $name);
        $parent->appendChild($element);

        return $element;
    }

    private function text(DOMDocument $xml, DOMElement $parent, string $namespace, string $name, string $value): DOMElement
    {
        $element = $this->node($xml, $parent, $namespace, $name);
        $element->appendChild($xml->createTextNode($value));

        return $element;
    }
}
