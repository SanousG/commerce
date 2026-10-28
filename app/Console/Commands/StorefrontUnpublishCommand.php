<?php

namespace App\Console\Commands;

use App\Modules\Storefront\Application\SetPublicStorePublication;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class StorefrontUnpublishCommand extends Command
{
    protected $signature = 'storefront:unpublish {tenant : Tenant UUID} {store : Store UUID}';

    protected $description = 'Unpublish a prepared Storefront';

    public function handle(SetPublicStorePublication $publication): int
    {
        try {
            $result = $publication->handle(
                (string) $this->argument('tenant'),
                (string) $this->argument('store'),
                false,
            );
        } catch (ModelNotFoundException) {
            $this->error('The active prepared Tenant and Store combination was not found.');

            return self::FAILURE;
        }

        $this->info("Storefront '{$result->public_handle}' is UNPUBLISHED.");

        return self::SUCCESS;
    }
}
