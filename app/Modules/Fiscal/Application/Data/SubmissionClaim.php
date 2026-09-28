<?php

namespace App\Modules\Fiscal\Application\Data;

use App\Modules\Fiscal\Domain\Models\FiscalSubmission;

/**
 * Result of the short transaction that decides which caller owns the single
 * in-flight SUNAT submission for a fiscal document.
 *
 * Only a claimed claim may reach the network; every other caller must return
 * the existing submission untouched.
 */
final readonly class SubmissionClaim
{
    public function __construct(public bool $claimed, public FiscalSubmission $submission) {}
}
