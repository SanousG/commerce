<?php

namespace App\Modules\Costing\Domain\Enums;

enum InventoryCostMovementType: string
{
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
    case FulfillmentCogs = 'FULFILLMENT_COGS';
    case ReturnRestock = 'RETURN_RESTOCK';
    case OpeningCost = 'OPENING_COST';
}
