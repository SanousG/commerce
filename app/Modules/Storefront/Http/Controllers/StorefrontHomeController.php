<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicCatalog;
use App\Modules\Storefront\Application\PublicStorePresenter;
use Inertia\Inertia;
use Inertia\Response;

final class StorefrontHomeController extends Controller
{
    public function __invoke(
        PublicStoreContext $context,
        PublicStorePresenter $stores,
        PublicCatalog $catalog,
    ): Response
    {
        $publication = $context->publication();
        $store = $context->store();
        abort_unless($publication !== null && $store !== null, 404);

        $storefront = $stores->present($publication);

        return Inertia::render('storefront/Home', [
            'store' => $storefront,
            ...$catalog->home($context),
            'localization' => [
                'locale' => $storefront['locale'],
                'fallback' => config('localization.fallback'),
                'supported' => config('localization.supported'),
            ],
        ]);
    }
}
