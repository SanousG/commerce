<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum FiscalDocumentStatus: string
{
    case Draft = 'DRAFT';
    case Generated = 'GENERATED';
    case SubmissionPending = 'SUBMISSION_PENDING';
    case Submitted = 'SUBMITTED';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}
