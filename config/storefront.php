<?php

return [
    'development_route_enabled' => (bool) env('STOREFRONT_DEVELOPMENT_ROUTE_ENABLED', false),

    'development_route_environments' => ['local', 'testing'],

    'development_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('STOREFRONT_DEVELOPMENT_HOSTS', 'localhost,127.0.0.1,::1')),
    ))),

    'reserved_handles' => [
        'admin',
        'api',
        'app',
        'assets',
        'auth',
        'commerce',
        'login',
        'register',
        'storefront',
        'support',
        'www',
    ],
];
