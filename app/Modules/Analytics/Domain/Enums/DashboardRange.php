<?php

namespace App\Modules\Analytics\Domain\Enums;

enum DashboardRange: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = '7d';
    case Last30Days = '30d';
    case ThisMonth = 'this_month';
}
