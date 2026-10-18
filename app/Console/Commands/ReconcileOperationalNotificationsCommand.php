<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Application\ReconcileOperationalNotifications;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReconcileOperationalNotificationsCommand extends Command
{
    protected $signature = 'notifications:reconcile {--tenant= : Reconcile only this tenant UUID}';

    protected $description = 'Reconcile active operational admin notifications';

    public function handle(ReconcileOperationalNotifications $reconciler): int
    {
        $tenant = trim((string) $this->option('tenant'));
        $tenantIds = DB::table('tenants')->where('status', TenantStatus::Active->value)
            ->when($tenant !== '', fn ($query) => $query->where('id', $tenant))
            ->orderBy('id')->pluck('id');

        if ($tenant !== '' && $tenantIds->isEmpty()) {
            $this->error('The active tenant was not found.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($tenantIds as $tenantId) {
            try {
                $result = $reconciler->reconcile((string) $tenantId);
                $this->line("{$tenantId}: {$result['conditions']} conditions, {$result['deliveries']} deliveries, {$result['resolved']} resolved");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$tenantId}: reconciliation failed ({$exception->getMessage()})");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
