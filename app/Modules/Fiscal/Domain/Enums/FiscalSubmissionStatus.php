<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum FiscalSubmissionStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Error = 'ERROR';
    case Unknown = 'UNKNOWN';
}
