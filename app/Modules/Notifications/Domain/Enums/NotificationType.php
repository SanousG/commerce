<?php

namespace App\Modules\Notifications\Domain\Enums;

enum NotificationType: string
{
    case LowStock = 'LOW_STOCK';
    case OutOfStock = 'OUT_OF_STOCK';
    case PaymentPending = 'PAYMENT_PENDING';
    case PaymentCaptured = 'PAYMENT_CAPTURED';
    case RefundPending = 'REFUND_PENDING';
    case RefundCompleted = 'REFUND_COMPLETED';
    case ReturnRequested = 'RETURN_REQUESTED';
    case ReturnApproved = 'RETURN_APPROVED';
    case ReturnAwaitingInspection = 'RETURN_AWAITING_INSPECTION';
    case FulfillmentCompleted = 'FULFILLMENT_COMPLETED';
    case ShipmentReady = 'SHIPMENT_READY';
    case PurchaseOrderToReceive = 'PURCHASE_ORDER_TO_RECEIVE';
    case PurchaseReceiptPosted = 'PURCHASE_RECEIPT_POSTED';
    case OrderPlaced = 'ORDER_PLACED';
    case FiscalRejected = 'FISCAL_REJECTED';
    case FiscalUnknown = 'FISCAL_UNKNOWN';

    /**
     * Reserved for the invitation flow. It is not deliverable through the
     * in-app center because invitees are not tenant members yet (the ledger
     * constrains recipients to `tenant_members`).
     */
    case TeamInvitation = 'TEAM_INVITATION';
}
