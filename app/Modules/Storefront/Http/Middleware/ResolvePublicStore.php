<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Storefront\Application\PublicStoreLocale;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolvePublicStore
{
    public function __construct(
        private readonly PublicStoreContext $context,
        private readonly PublicStoreLocale $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $handle = $request->route('publicHandle');
        abort_unless(is_string($handle), 404);

        $publication = StorefrontPublication::query()
            ->where('public_handle', $handle)
            ->where('status', StorefrontPublicationStatus::Published)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->whereHas('store', fn ($query) => $query->where('status', StoreStatus::Active))
            ->with([
                'tenant',
                'store.settings.logo.derivatives',
                'store.settings.favicon.derivatives',
            ])
            ->firstOrFail();

        $this->context->set($publication);
        $previousLocale = app()->getLocale();
        app()->setLocale($this->locales->resolve($publication->store->locale));

        try {
            return $next($request);
        } finally {
            app()->setLocale($previousLocale);
            $this->context->clear();
        }
    }
}
