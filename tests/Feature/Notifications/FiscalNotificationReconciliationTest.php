<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use App\Modules\Notifications\Application\ReconcileOperationalNotifications;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use Tests\Feature\TaxFiscal\TaxFiscalFeatureTestCase;

class FiscalNotificationReconciliationTest extends TaxFiscalFeatureTestCase
{
    public function test_latest_rejected_and_unknown_submission_reconcile_and_accepted_resolves(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $owner);
        $document = $this->generateReceipt($tenant, $store, $order, $owner);
        $submission = FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(),
            'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 1,
            'status' => FiscalSubmissionStatus::Rejected,
            'provider' => 'SUNAT_DIRECT',
        ]);
        $reconciler = app(ReconcileOperationalNotifications::class);

        $reconciler->reconcile($tenant->getKey());
        $notification = AdminNotification::query()
            ->where('deduplication_key', 'condition:fiscal:'.$document->getKey())->sole();
        $this->assertSame(NotificationType::FiscalRejected, $notification->type);
        $this->assertSame(NotificationSeverity::Critical, $notification->severity);

        $submission->update(['status' => FiscalSubmissionStatus::Unknown]);
        $reconciler->reconcile($tenant->getKey());
        $this->assertSame(NotificationType::FiscalUnknown, $notification->fresh()->type);
        $this->assertSame(NotificationSeverity::Warning, $notification->fresh()->severity);
        $this->assertSame(1, AdminNotification::query()->where('deduplication_key', $notification->deduplication_key)->count());

        $submission->update(['status' => FiscalSubmissionStatus::Accepted]);
        $reconciler->reconcile($tenant->getKey());
        $this->assertNotNull($notification->fresh()->resolved_at);
    }
}
