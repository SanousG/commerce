<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Storefront\Application\PublicCatalog;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicStorePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StorefrontCatalogController extends Controller
{
    public function __invoke(
        Request $request,
        string $publicHandle,
        PublicStoreContext $context,
        PublicStorePresenter $stores,
        PublicCatalog $catalog,
        ?string $categorySlug = null,
    ): Response {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $publication = $context->publication();
        abort_unless($publication !== null, 404);
        $store = $stores->present($publication);

        return Inertia::render('storefront/Catalog', [
            'store' => $store,
            ...$catalog->catalog($context, trim((string) ($validated['q'] ?? '')), $categorySlug),
            'localization' => $this->localization($store['locale']),
        ]);
    }

    /** @return array<string, mixed> */
    private function localization(string $locale): array
    {
        return ['locale' => $locale, 'fallback' => config('localization.fallback'), 'supported' => config('localization.supported')];
    }
}
