<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Notifications\Domain\Enums\NotificationType;

/**
 * Static mapping between notification types, the permission a recipient needs to
 * open the linked resource, and the types that nobody may mute.
 *
 * The permission is re-checked when an email is delivered so a revoked grant
 * cannot keep receiving protected content. It is a coarse "can open the action
 * URL" gate, not a re-derivation of the exact creation-time grant.
 */
final class NotificationRouting
{
    /** Types that are mandatory for administration/security and cannot be muted. */
    public const MANDATORY_TYPES = [
        NotificationType::FiscalRejected->value,
        NotificationType::FiscalUnknown->value,
    ];

    public static function permission(NotificationType $type): ?PermissionKey
    {
        return match ($type) {
            NotificationType::LowStock, NotificationType::OutOfStock => PermissionKey::InventoryView,
            NotificationType::PaymentPending, NotificationType::PaymentCaptured => PermissionKey::PaymentsView,
            NotificationType::RefundPending, NotificationType::RefundCompleted => PermissionKey::RefundsView,
            NotificationType::ReturnRequested, NotificationType::ReturnApproved,
            NotificationType::ReturnAwaitingInspection => PermissionKey::ReturnsView,
            NotificationType::FulfillmentCompleted => PermissionKey::FulfillmentsView,
            NotificationType::ShipmentReady => PermissionKey::ShipmentsView,
            NotificationType::PurchaseOrderToReceive, NotificationType::PurchaseReceiptPosted => PermissionKey::ProcurementView,
            NotificationType::OrderPlaced => PermissionKey::OrdersView,
            NotificationType::FiscalRejected, NotificationType::FiscalUnknown => PermissionKey::FiscalDocumentsView,
            NotificationType::TeamInvitation => null,
        };
    }

    public static function isMandatory(NotificationType $type): bool
    {
        return in_array($type->value, self::MANDATORY_TYPES, true);
    }
}
