<?php

namespace Tests\Feature\Storefront;

class DevelopmentStorefrontRouteTest extends StorefrontFeatureTestCase
{
    public function test_untrusted_host_is_rejected_before_public_resolution(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $this->publish($store, 'host-safe');

        $this->get('http://untrusted.example/tienda/host-safe')->assertNotFound();
    }

    public function test_temporary_route_is_disabled_when_configuration_is_off(): void
    {
        config(['storefront.development_route_enabled' => false]);

        $this->get('/tienda/anything')->assertNotFound();
    }

    public function test_temporary_route_remains_disabled_in_production_even_if_flag_is_on(): void
    {
        config([
            'app.env' => 'production',
            'storefront.development_route_enabled' => true,
        ]);

        $this->get('/tienda/anything')->assertNotFound();
    }
}
