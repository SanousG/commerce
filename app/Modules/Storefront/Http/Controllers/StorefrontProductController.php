<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Application\PublicCatalog;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicStorePresenter;
use Inertia\Inertia;
use Inertia\Response;

final class StorefrontProductController extends Controller
{
    public function __invoke(
        string $publicHandle,
        string $listingSlug,
        PublicStoreContext $context,
        PublicStorePresenter $stores,
        PublicCatalog $catalog,
    ): Response {
        $publication = $context->publication();
        abort_unless($publication !== null, 404);
        $store = $stores->present($publication);

        return Inertia::render('storefront/Product', [
            'store' => $store,
            'product' => $catalog->product($context, $listingSlug),
            'localization' => ['locale' => $store['locale'], 'fallback' => config('localization.fallback'), 'supported' => config('localization.supported')],
        ]);
    }
}
