<?php

namespace Tests\Unit\Storefront;

use App\Modules\Storefront\Application\PublicStoreLocale;
use Tests\TestCase;

class PublicStoreLocaleTest extends TestCase
{
    public function test_it_maps_store_locales_to_supported_vue_catalogs(): void
    {
        $resolver = app(PublicStoreLocale::class);

        $this->assertSame('es-PE', $resolver->resolve('es_PE'));
        $this->assertSame('en', $resolver->resolve('en_US'));
    }

    public function test_it_uses_the_safe_fallback_for_an_unsupported_locale(): void
    {
        $this->assertSame('es-PE', app(PublicStoreLocale::class)->resolve('fr_FR'));
    }
}
