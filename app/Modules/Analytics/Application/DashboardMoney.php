<?php

namespace App\Modules\Analytics\Application;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DashboardMoney
{
    public static function value(mixed $value): string
    {
        return (string) BigDecimal::of($value ?? 0)->toScale(4, RoundingMode::HalfUp);
    }
}
