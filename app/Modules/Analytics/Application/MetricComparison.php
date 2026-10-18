<?php

namespace App\Modules\Analytics\Application;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds a current/previous comparison for a single metric.
 *
 * The percentage is withheld (null) when the previous value is zero, because an
 * undefined or infinite growth rate would be misleading. Consumers must render
 * a neutral "no previous baseline" state in that case.
 */
final class MetricComparison
{
    /** @return array{current: string, previous: string, delta: string, percent: string|null} */
    public static function money(string $current, string $previous): array
    {
        $currentValue = BigDecimal::of($current)->toScale(4, RoundingMode::HalfUp);
        $previousValue = BigDecimal::of($previous)->toScale(4, RoundingMode::HalfUp);

        return [
            'current' => (string) $currentValue,
            'previous' => (string) $previousValue,
            'delta' => (string) $currentValue->minus($previousValue)->toScale(4, RoundingMode::HalfUp),
            'percent' => self::percent((string) $currentValue, (string) $previousValue),
        ];
    }

    /** @return array{current: int, previous: int, delta: int, percent: string|null} */
    public static function count(int $current, int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $current - $previous,
            'percent' => self::percent((string) $current, (string) $previous),
        ];
    }

    private static function percent(string $current, string $previous): ?string
    {
        $previousValue = BigDecimal::of($previous);

        if ($previousValue->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($current)
            ->minus($previousValue)
            ->multipliedBy(100)
            ->dividedBy($previousValue, 2, RoundingMode::HalfUp);
    }
}
