<?php

namespace App\Modules\Notifications\Domain\Enums;

enum NotificationType: string
{
    case LowStock = 'LOW_STOCK';
    case OutOfStock = 'OUT_OF_STOCK';
    case PaymentPending = 'PAYMENT_PENDING';
    case RefundPending = 'REFUND_PENDING';
    case ReturnAwaitingInspection = 'RETURN_AWAITING_INSPECTION';
    case PurchaseOrderToReceive = 'PURCHASE_ORDER_TO_RECEIVE';
    case FiscalRejected = 'FISCAL_REJECTED';
    case FiscalUnknown = 'FISCAL_UNKNOWN';
    case TeamInvitation = 'TEAM_INVITATION';
}
