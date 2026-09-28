<?php

namespace App\Modules\Inventory\Domain\Enums;

enum InventoryMovementType: string
{
    case Opening = 'OPENING';
    case AdjustmentIn = 'ADJUSTMENT_IN';
    case AdjustmentOut = 'ADJUSTMENT_OUT';
    case TransferIn = 'TRANSFER_IN';
    case TransferOut = 'TRANSFER_OUT';
    case ReservationCommit = 'RESERVATION_COMMIT';
    case ReturnRestock = 'RETURN_RESTOCK';
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
}
