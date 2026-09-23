<?php

return [
    'default_currency' => env('COMMERCE_DEFAULT_CURRENCY', 'PEN'),
    'default_country' => env('COMMERCE_DEFAULT_COUNTRY', 'PE'),
    'default_inventory_location_name' => env('COMMERCE_DEFAULT_INVENTORY_LOCATION_NAME', 'Almacén principal'),
    'default_inventory_location_code' => env('COMMERCE_DEFAULT_INVENTORY_LOCATION_CODE', 'MAIN'),
    // Cart stock checks aggregate active inventory locations; allocation remains a Checkout concern.
    'cart_ttl_hours' => (int) env('COMMERCE_CART_TTL_HOURS', 72),
    'cart_max_item_quantity' => (int) env('COMMERCE_CART_MAX_ITEM_QUANTITY', 9999),
    'checkout_ttl_minutes' => (int) env('COMMERCE_CHECKOUT_TTL_MINUTES', 15),
    'currencies' => [
        'PEN', 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'BRL', 'MXN', 'COP', 'CLP',
    ],
];
