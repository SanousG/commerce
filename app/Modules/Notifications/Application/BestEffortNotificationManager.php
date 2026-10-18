<?php

namespace App\Modules\Notifications\Application;

use Illuminate\Support\Collection;
use Throwable;

final class BestEffortNotificationManager
{
    public function __construct(private readonly NotificationManager $notifications) {}

    /** @return Collection<int, mixed>|null */
    public function deliverEvent(...$arguments): ?Collection
    {
        try {
            return $this->notifications->deliverEvent(...$arguments);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** @return Collection<int, mixed>|null */
    public function deliverCondition(...$arguments): ?Collection
    {
        try {
            return $this->notifications->deliverCondition(...$arguments);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
