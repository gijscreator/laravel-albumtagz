<?php
return [
    'paths' => ['*'], // Apply CORS to all routes
    'allowed_methods' => ['*'], // Allow all HTTP methods
    'allowed_origins' => ['*'], // Allow all origins
    'allowed_origins_patterns' => [], // No regex patterns
    'allowed_headers' => ['*'], // Allow all headers
    'exposed_headers' => [], // No custom headers exposed
    'max_age' => 0, // Cache duration for preflight requests
    'supports_credentials' => false, // Disable credentials with wildcard origins
];
