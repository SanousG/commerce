<?php

namespace App\Console\Commands;

use App\Modules\Storefront\Application\PreparePublicStore;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class StorefrontPrepareCommand extends Command
{
    protected $signature = 'storefront:prepare {tenant : Tenant UUID} {store : Store UUID} {publicHandle : Desired globally unique public handle}';

    protected $description = 'Prepare an active Store for public access without publishing it';

    public function handle(PreparePublicStore $prepare): int
    {
        try {
            $publication = $prepare->handle(
                (string) $this->argument('tenant'),
                (string) $this->argument('store'),
                (string) $this->argument('publicHandle'),
            );
        } catch (InvalidArgumentException|ValidationException $exception) {
            $this->error($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : $exception->getMessage());

            return self::FAILURE;
        } catch (ModelNotFoundException) {
            $this->error('The active Tenant and Store combination was not found.');

            return self::FAILURE;
        }

        $this->info("Storefront prepared as '{$publication->public_handle}' and remains UNPUBLISHED.");

        return self::SUCCESS;
    }
}
