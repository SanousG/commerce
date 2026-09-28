<?php

namespace App\Modules\Returns\Domain\Enums;

enum ReturnStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Received = 'RECEIVED';
    case Inspecting = 'INSPECTING';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
