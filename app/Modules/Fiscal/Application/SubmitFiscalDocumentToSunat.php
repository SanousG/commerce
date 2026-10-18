<?php

namespace App\Modules\Fiscal\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Fiscal\Application\Contracts\DigitalCertificateProvider;
use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use App\Modules\Fiscal\Application\Contracts\FiscalDocumentSerializer;
use App\Modules\Fiscal\Application\Contracts\SunatCredentialsProvider;
use App\Modules\Fiscal\Application\Contracts\SunatTransport;
use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use App\Modules\Fiscal\Application\Data\SubmissionClaim;
use App\Modules\Fiscal\Application\Data\XmlValidationResult;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentType;
use App\Modules\Fiscal\Domain\Enums\FiscalProvider;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Fiscal\Domain\Enums\SubmissionStage;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatCdrParser;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatDeliveryPhase;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatDocumentFileName;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatTransportException;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatZipPackager;
use App\Modules\Fiscal\Infrastructure\Sunat\UblSchemaValidator;
use App\Modules\Fiscal\Infrastructure\Sunat\XmlDigitalSigner;
use App\Modules\Fiscal\Infrastructure\Sunat\XmlSignatureVerifier;
use App\Modules\Notifications\Application\BestEffortNotificationManager;
use App\Modules\Notifications\Application\NotificationRecipientResolver;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class SubmitFiscalDocumentToSunat
{
    public function __construct(
        private FiscalDocumentSerializer $serializer,
        private UblSchemaValidator $schemas,
        private XmlDigitalSigner $signer,
        private XmlSignatureVerifier $signatures,
        private SunatZipPackager $packager,
        private FiscalArtifactStorage $artifacts,
        private SunatTransport $transport,
        private SunatCredentialsProvider $credentials,
        private DigitalCertificateProvider $certificates,
        private SunatCdrParser $cdr,
        private SunatDocumentFileName $fileNames,
        private AuditRecorder $audit,
        private NotificationRecipientResolver $recipients,
        private BestEffortNotificationManager $bestEffortNotifications,
    ) {}

    public function handle(string $tenantId, string $storeId, string $documentId, ?User $actor = null): FiscalSubmission
    {
        $document = FiscalDocument::query()->where('tenant_id', $tenantId)->where('store_id', $storeId)
            ->with(['items', 'tenant', 'store'])->findOrFail($documentId);
        $this->assertEligible($document);
        if ($document->currency !== 'PEN') {
            throw ValidationException::withMessages(['document' => 'SUNAT_CURRENCY_NOT_SUPPORTED']);
        }

        try {
            $unsigned = $this->serializer->buildPayload($document);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }
        $this->assertValid($this->schemas->validateInvoice($unsigned->contents), 'UBL_VALIDATION_FAILED');

        $credentials = $this->credentials->credentialsFor($tenantId);
        $certificate = $this->certificates->certificateFor($tenantId);
        $this->assertIssuerAlignment($document, $certificate, $credentials->username);

        $signedXml = $this->signer->sign($unsigned->contents, $certificate);
        if (! $this->signatures->verify($signedXml, $certificate)) {
            throw ValidationException::withMessages(['document' => 'SIGNATURE_FAILED']);
        }
        $this->assertValid($this->schemas->validateInvoice($signedXml), 'SIGNED_UBL_VALIDATION_FAILED');
        $zip = $this->packager->package($unsigned->fileName, $signedXml);
        $requestHash = hash('sha256', $signedXml);

        $claim = $this->claim($tenantId, $storeId, $documentId, $requestHash, $credentials->environment->value);
        if (! $claim->claimed) {
            return $claim->submission;
        }
        $submission = $claim->submission;

        $stage = SubmissionStage::Preparing;
        try {
            $directory = 'attempt-'.$submission->attempt_number;
            $unsignedKey = $this->artifacts->put($tenantId, $documentId, $directory, $unsigned->fileName, $unsigned->contents);
            $signedKey = $this->artifacts->put($tenantId, $documentId, $directory, 'signed-'.$unsigned->fileName, $signedXml);
            $zipName = $this->fileNames->zip($document);
            $zipKey = $this->artifacts->put($tenantId, $documentId, $directory, $zipName, $zip);
            $submission = $this->markSent(
                $document->tenant,
                $document->store,
                $document,
                $submission,
                $actor,
                $unsignedKey,
                $signedKey,
                $zipKey,
            );

            $stage = SubmissionStage::InFlight;
            $started = hrtime(true);
            $result = $this->transport->sendBill($tenantId, $zipName, $zip);
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);
            if ($result->cdrZip === null) {
                throw new SunatTransportException('SUNAT_CDR_MISSING', SunatDeliveryPhase::DeliveryUnknown);
            }
            $cdrZipKey = $this->artifacts->put($tenantId, $documentId, $directory, 'R-'.$zipName, $result->cdrZip);
            $cdrXml = $this->cdr->extractXml($result->cdrZip);
            $cdrXmlKey = $this->artifacts->put($tenantId, $documentId, $directory, 'R-'.$unsigned->fileName, $cdrXml);
            $parsed = $this->cdr->parseXml($cdrXml);

            $stage = SubmissionStage::Responded;

            return DB::transaction(function () use ($tenantId, $submission, $document, $documentId, $parsed, $cdrZipKey, $cdrXmlKey, $duration, $actor): FiscalSubmission {
                $lockedSubmission = FiscalSubmission::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($submission->getKey());
                $lockedDocument = FiscalDocument::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($documentId);
                $accepted = $parsed->accepted();
                $status = $accepted ? FiscalSubmissionStatus::Accepted : FiscalSubmissionStatus::Rejected;
                $lockedSubmission->update([
                    'status' => $status->value,
                    'response_code' => $parsed->responseCode,
                    'response_message' => $parsed->description,
                    'response_description' => $parsed->description,
                    'observations' => $parsed->observations,
                    'cdr_path' => $cdrZipKey,
                    'cdr_storage_key' => $cdrZipKey,
                    'cdr_xml_storage_key' => $cdrXmlKey,
                    'duration_ms' => $duration,
                    'responded_at' => now(),
                ]);
                $lockedDocument->update($accepted ? [
                    'status' => FiscalDocumentStatus::Accepted->value,
                    'submitted_at' => $lockedSubmission->submitted_at,
                    'accepted_at' => now(),
                ] : [
                    'status' => FiscalDocumentStatus::Rejected->value,
                    'submitted_at' => $lockedSubmission->submitted_at,
                    'rejected_at' => now(),
                ]);
                $this->recordResultAudit(
                    $document->tenant,
                    $document->store,
                    $lockedDocument,
                    $lockedSubmission,
                    $status,
                    $actor,
                );
                $this->syncFiscalNotificationAfterCommit($lockedDocument, $lockedSubmission, $status);

                return $lockedSubmission->refresh();
            });
        } catch (SunatTransportException $exception) {
            $status = match ($exception->phase) {
                SunatDeliveryPhase::PreDelivery, SunatDeliveryPhase::RemoteResponse => FiscalSubmissionStatus::Error,
                SunatDeliveryPhase::DeliveryUnknown => FiscalSubmissionStatus::Unknown,
            };
            $this->recordFailure($tenantId, $submission, $document, $status, $exception, $stage, $actor);
            throw $exception;
        } catch (Throwable $exception) {
            $status = $stage === SubmissionStage::Preparing ? FiscalSubmissionStatus::Error : FiscalSubmissionStatus::Unknown;
            $this->recordFailure($tenantId, $submission, $document, $status, $exception, $stage, $actor);
            throw $exception;
        }
    }

    /**
     * Short transaction that serializes concurrent callers on the document row
     * and only ever lets the creator of the submission row reach the network.
     */
    private function claim(string $tenantId, string $storeId, string $documentId, string $requestHash, string $environment): SubmissionClaim
    {
        try {
            return DB::transaction(function () use ($tenantId, $storeId, $documentId, $requestHash, $environment): SubmissionClaim {
                $locked = FiscalDocument::query()->where('tenant_id', $tenantId)->where('store_id', $storeId)
                    ->lockForUpdate()->findOrFail($documentId);
                $this->assertEligible($locked);

                $existing = $locked->submissions()->where('request_hash', $requestHash)
                    ->whereIn('status', [
                        FiscalSubmissionStatus::Pending->value,
                        FiscalSubmissionStatus::Processing->value,
                        FiscalSubmissionStatus::Sent->value,
                        FiscalSubmissionStatus::Accepted->value,
                        FiscalSubmissionStatus::Unknown->value,
                    ])->latest('attempt_number')->first();
                if ($existing !== null) {
                    return new SubmissionClaim(false, $existing);
                }
                $inFlight = $locked->submissions()->whereIn('status', [
                    FiscalSubmissionStatus::Pending->value,
                    FiscalSubmissionStatus::Processing->value,
                    FiscalSubmissionStatus::Sent->value,
                    FiscalSubmissionStatus::Unknown->value,
                ])->first();
                if ($inFlight !== null) {
                    return new SubmissionClaim(false, $inFlight);
                }

                $attempt = ((int) $locked->submissions()->max('attempt_number')) + 1;
                $locked->update(['status' => FiscalDocumentStatus::SubmissionPending->value]);

                $created = $locked->submissions()->create([
                    'tenant_id' => $tenantId,
                    'attempt_number' => $attempt,
                    'status' => FiscalSubmissionStatus::Processing->value,
                    'provider' => FiscalProvider::SunatDirect->value,
                    'environment' => $environment,
                    'request_hash' => $requestHash,
                ]);

                return new SubmissionClaim(true, $created);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isInFlightIndexViolation($exception)) {
                throw $exception;
            }
            $inFlight = FiscalSubmission::query()->where('tenant_id', $tenantId)
                ->where('fiscal_document_id', $documentId)
                ->whereIn('status', [
                    FiscalSubmissionStatus::Pending->value,
                    FiscalSubmissionStatus::Processing->value,
                    FiscalSubmissionStatus::Sent->value,
                    FiscalSubmissionStatus::Unknown->value,
                ])->latest('attempt_number')->first();
            if ($inFlight === null) {
                throw $exception;
            }

            return new SubmissionClaim(false, $inFlight);
        }
    }

    private function markSent(
        Tenant $tenant,
        Store $store,
        FiscalDocument $document,
        FiscalSubmission $submission,
        ?User $actor,
        string $unsignedKey,
        string $signedKey,
        string $zipKey,
    ): FiscalSubmission {
        return DB::transaction(function () use ($tenant, $store, $document, $submission, $actor, $unsignedKey, $signedKey, $zipKey): FiscalSubmission {
            $locked = FiscalSubmission::query()->where('tenant_id', $tenant->getKey())
                ->where('fiscal_document_id', $document->getKey())
                ->lockForUpdate()->findOrFail($submission->getKey());
            $locked->update([
                'status' => FiscalSubmissionStatus::Sent->value,
                'unsigned_xml_storage_key' => $unsignedKey,
                'signed_xml_storage_key' => $signedKey,
                'zip_storage_key' => $zipKey,
                'request_storage_key' => $zipKey,
                'submitted_at' => now(),
            ]);
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::FiscalDocumentSubmitted,
                AuditSubjectType::FiscalDocument,
                (string) $document->getKey(),
                [
                    'submission_id' => $locked->getKey(),
                    'status' => FiscalSubmissionStatus::Sent->value,
                    'environment' => $locked->environment,
                ],
                $store,
                $actor,
                idempotencyKey: 'fiscal.submitted:'.$locked->getKey(),
                subjectLabel: $document->full_number,
            );

            return $locked->refresh();
        });
    }

    private function assertEligible(FiscalDocument $document): void
    {
        if ($document->document_type !== FiscalDocumentType::Invoice) {
            throw ValidationException::withMessages(['document' => 'Boletas are prepared and reported through a Daily Summary; individual submission is blocked.']);
        }
        if ($document->status === FiscalDocumentStatus::Rejected) {
            throw ValidationException::withMessages(['document' => 'A SUNAT-rejected document cannot be resubmitted automatically.']);
        }
        if (! in_array($document->status, [FiscalDocumentStatus::Generated, FiscalDocumentStatus::SubmissionPending], true)) {
            throw ValidationException::withMessages(['document' => 'The fiscal document is not eligible for submission.']);
        }
    }

    private function assertIssuerAlignment(FiscalDocument $document, DigitalCertificate $certificate, string $username): void
    {
        if ($certificate->subjectRuc !== null && $certificate->subjectRuc !== $document->issuer_tax_id) {
            throw ValidationException::withMessages(['document' => 'SUNAT_CERTIFICATE_RUC_MISMATCH']);
        }
        if (preg_match('/(?<!\d)(\d{11})(?!\d)/', $username, $matches) === 1 && $matches[1] !== $document->issuer_tax_id) {
            throw ValidationException::withMessages(['document' => 'SUNAT_SOL_USERNAME_RUC_MISMATCH']);
        }
    }

    private function recordFailure(
        string $tenantId,
        FiscalSubmission $submission,
        FiscalDocument $document,
        FiscalSubmissionStatus $status,
        Throwable $exception,
        SubmissionStage $stage,
        ?User $actor,
    ): void {
        $message = $exception instanceof SunatTransportException && $exception->detail !== null
            ? $exception->detail
            : mb_substr($exception->getMessage(), 0, 500);
        DB::transaction(function () use ($tenantId, $submission, $document, $status, $exception, $message, $actor): void {
            $lockedSubmission = FiscalSubmission::query()->where('tenant_id', $tenantId)
                ->lockForUpdate()->findOrFail($submission->getKey());
            $lockedDocument = FiscalDocument::query()->where('tenant_id', $tenantId)
                ->lockForUpdate()->findOrFail($document->getKey());
            $wasUnknown = $lockedSubmission->status === FiscalSubmissionStatus::Unknown;
            $lockedSubmission->update([
                'status' => $status->value,
                'error_class' => $exception::class,
                'response_message' => $message,
                'responded_at' => $lockedSubmission->submitted_at !== null ? now() : null,
            ]);
            $lockedDocument->update($status === FiscalSubmissionStatus::Unknown ? [
                'status' => FiscalDocumentStatus::Submitted->value,
                'submitted_at' => $lockedSubmission->submitted_at ?? now(),
            ] : [
                'status' => FiscalDocumentStatus::Generated->value,
            ]);

            if ($status === FiscalSubmissionStatus::Unknown && ! $wasUnknown) {
                $this->recordResultAudit(
                    $document->tenant,
                    $document->store,
                    $lockedDocument,
                    $lockedSubmission,
                    $status,
                    $actor,
                );
                $this->syncFiscalNotificationAfterCommit($lockedDocument, $lockedSubmission, $status);
            }
        });

        Log::warning('SUNAT submission failed', [
            'tenant_id' => $tenantId,
            'fiscal_document_id' => $document->getKey(),
            'submission_id' => $submission->getKey(),
            'stage' => $stage->value,
            'status' => $status->value,
            'phase' => $exception instanceof SunatTransportException ? $exception->phase->value : null,
            'error' => $message,
        ]);
    }

    private function recordResultAudit(
        Tenant $tenant,
        Store $store,
        FiscalDocument $document,
        FiscalSubmission $submission,
        FiscalSubmissionStatus $status,
        ?User $actor,
    ): void {
        $action = match ($status) {
            FiscalSubmissionStatus::Accepted => AuditAction::FiscalSubmissionAccepted,
            FiscalSubmissionStatus::Rejected => AuditAction::FiscalSubmissionRejected,
            FiscalSubmissionStatus::Unknown => AuditAction::FiscalSubmissionUnknown,
            default => throw new InvalidArgumentException('The fiscal result status is not auditable.'),
        };
        $this->audit->record(
            $tenant,
            $actor === null ? AuditActorType::System : AuditActorType::User,
            $action,
            AuditSubjectType::FiscalDocument,
            (string) $document->getKey(),
            [
                'submission_id' => $submission->getKey(),
                'status' => $status->value,
                'response_code' => $submission->response_code,
                'environment' => $submission->environment,
            ],
            $store,
            $actor,
            idempotencyKey: 'fiscal.result:'.$status->value.':'.$submission->getKey(),
            subjectLabel: $document->full_number,
        );
    }

    private function syncFiscalNotificationAfterCommit(
        FiscalDocument $document,
        FiscalSubmission $submission,
        FiscalSubmissionStatus $status,
    ): void {
        DB::afterCommit(function () use ($document, $submission, $status): void {
            try {
                if ($status === FiscalSubmissionStatus::Accepted) {
                    DB::table('admin_notifications')
                        ->where('tenant_id', $document->tenant_id)
                        ->where('store_id', $document->store_id)
                        ->where('deduplication_key', 'condition:fiscal:'.$document->getKey())
                        ->whereNull('resolved_at')
                        ->whereNull('dismissed_at')
                        ->update(['resolved_at' => now(), 'updated_at' => now()]);

                    return;
                }

                if (! in_array($status, [FiscalSubmissionStatus::Rejected, FiscalSubmissionStatus::Unknown], true)) {
                    return;
                }

                $recipients = $this->recipients->userIds($document->tenant_id, [
                    PermissionKey::FiscalDocumentsManage,
                    PermissionKey::FiscalDocumentsView,
                ]);
                $rejected = $status === FiscalSubmissionStatus::Rejected;
                $this->bestEffortNotifications->deliverCondition(
                    $document->tenant_id,
                    $document->store_id,
                    $recipients,
                    'fiscal:'.$document->getKey(),
                    $rejected ? NotificationType::FiscalRejected : NotificationType::FiscalUnknown,
                    $rejected ? NotificationSeverity::Critical : NotificationSeverity::Warning,
                    $rejected ? 'Fiscal document rejected' : 'Fiscal document status unknown',
                    "Fiscal document {$document->full_number} requires attention.",
                    '/admin/fiscal-documents/'.$document->getKey(),
                    [
                        'submission_id' => $submission->getKey(),
                        'status' => $status->value,
                        'response_code' => $submission->response_code,
                        'environment' => $submission->environment,
                    ],
                    AuditSubjectType::FiscalDocument->value,
                    (string) $document->getKey(),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function isInFlightIndexViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains($exception->getMessage(), 'fiscal_submissions_one_in_flight');
    }

    private function assertValid(XmlValidationResult $result, string $code): void
    {
        if (! $result->valid) {
            throw ValidationException::withMessages(['document' => $code, 'xml' => array_slice($result->errors, 0, 20)]);
        }
    }
}
