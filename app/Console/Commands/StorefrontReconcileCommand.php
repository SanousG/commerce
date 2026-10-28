<?php

namespace App\Console\Commands;

use App\Modules\Storefront\Application\ReconcileStorefrontPublications;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class StorefrontReconcileCommand extends Command
{
    protected $signature = 'storefront:reconcile {--tenant= : Reconcile only this Tenant UUID}';

    protected $description = 'Idempotently prepare every Store that has no Storefront publication';

    public function handle(ReconcileStorefrontPublications $reconcile): int
    {
        $tenantId = $this->option('tenant');

        if (filled($tenantId) && ! Str::isUuid((string) $tenantId)) {
            $this->error('The requested Tenant UUID is invalid.');

            return self::FAILURE;
        }

        $tenant = filled($tenantId) ? Tenant::query()->find($tenantId) : null;

        if (filled($tenantId) && $tenant === null) {
            $this->error('The requested Tenant was not found.');

            return self::FAILURE;
        }

        $count = $reconcile->handle($tenant);
        $this->info("Reconciled {$count} Storefront publication(s) as UNPUBLISHED.");

        return self::SUCCESS;
    }
}
