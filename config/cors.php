<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Allow all methods (GET/POST/OPTIONS are needed)
    'allowed_methods' => ['*'],

    // Exact origins you use in production
    'allowed_origins' => [
        'https://www.musictags.eu',
        'https://musictags.eu',
        'https://create.musictags.eu',
        // add any custom storefront host you actually use, e.g.:
        // 'https://shop.musictags.eu',
        'https://745b68-a1.myshopify.com',
        // local/dev:
        'http://127.0.0.1:3002',
    ],

    // Wildcard patterns (useful if your myshopify subdomain might change)
    'allowed_origins_patterns' => [
        // allow any myshopify storefront subdomain
        '^https:\/\/[a-z0-9-]+\.myshopify\.com$',
        // optional: allow any subdomain of musictags.eu (e.g., staging)
        '^https:\/\/([a-z0-9-]+\.)?albumtagz\.com$',
    ],

    // Headers — keep '*' so custom 'Api-Key' is accepted
    'allowed_headers' => ['*'],

    // If you don’t need to read custom response headers, leave empty
    'exposed_headers' => [],

    // Cache preflight for a day (seconds)
    'max_age' => 86400,

    // You’re using an Api-Key header, not cookies — leave this false
    'supports_credentials' => false,
];
