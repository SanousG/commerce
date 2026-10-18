<?php

namespace App\Modules\Audit\Domain\Enums;

enum AuditSubjectType: string
{
    case Tenant = 'TENANT';
    case TenantMember = 'TENANT_MEMBER';
    case TenantInvitation = 'TENANT_INVITATION';
    case Store = 'STORE';
    case User = 'USER';
    case Category = 'CATEGORY';
    case Product = 'PRODUCT';
    case ProductVariant = 'PRODUCT_VARIANT';
    case PriceList = 'PRICE_LIST';
    case Promotion = 'PROMOTION';
    case InventoryLocation = 'INVENTORY_LOCATION';
    case InventoryLevel = 'INVENTORY_LEVEL';
    case InventoryTransfer = 'INVENTORY_TRANSFER';
    case InventoryCostMovement = 'INVENTORY_COST_MOVEMENT';
    case StoreListing = 'STORE_LISTING';
    case Customer = 'CUSTOMER';
    case Cart = 'CART';
    case Checkout = 'CHECKOUT';
    case Order = 'ORDER';
    case Payment = 'PAYMENT';
    case PaymentSettings = 'PAYMENT_SETTINGS';
    case Refund = 'REFUND';
    case Fulfillment = 'FULFILLMENT';
    case Shipment = 'SHIPMENT';
    case ReturnRequest = 'RETURN';
    case ReturnItem = 'RETURN_ITEM';
    case Exchange = 'EXCHANGE';
    case Supplier = 'SUPPLIER';
    case PurchaseOrder = 'PURCHASE_ORDER';
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
    case TaxCategory = 'TAX_CATEGORY';
    case TaxRate = 'TAX_RATE';
    case FiscalConfiguration = 'FISCAL_CONFIGURATION';
    case FiscalSettings = 'FISCAL_SETTINGS';
    case FiscalDocument = 'FISCAL_DOCUMENT';
    case FiscalSubmission = 'FISCAL_SUBMISSION';
    case Settings = 'SETTINGS';
}
