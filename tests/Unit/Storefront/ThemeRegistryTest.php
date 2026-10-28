<?php

namespace Tests\Unit\Storefront;

use App\Modules\Storefront\Application\ThemeRegistry;
use Tests\TestCase;

class ThemeRegistryTest extends TestCase
{
    public function test_it_resolves_essential_as_the_safe_default(): void
    {
        $registry = new ThemeRegistry;

        $this->assertSame('essential', $registry->resolve());
        $this->assertSame('essential', $registry->resolve('unknown-theme'));
    }
}
