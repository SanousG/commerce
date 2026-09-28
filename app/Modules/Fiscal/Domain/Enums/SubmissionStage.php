<?php

namespace App\Modules\Fiscal\Domain\Enums;

enum SubmissionStage: string
{
    /** Local preparation only: nothing has been handed to the transport. */
    case Preparing = 'PREPARING';

    /** The transport call is in progress or its result is being processed. */
    case InFlight = 'IN_FLIGHT';

    /** SUNAT answered and only the local persistence of the outcome remains. */
    case Responded = 'RESPONDED';
}
