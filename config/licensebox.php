<?php

return [

    /*
    | Default values for settings that admins can override from the Settings page.
    */
    'defaults' => [
        'site_name' => env('APP_NAME', 'LicenseBox'),
        'api_rate_limit' => 60,           // requests per minute per IP
        'verify_interval_days' => 7,      // how often the SDK must re-check online
        'offline_grace_days' => 14,       // extra days the local key stays valid when the server is unreachable
        'download_link_minutes' => 10,    // lifetime of signed update download URLs
        'log_retention_days' => 90,
    ],

    /*
    | RSA keypair used to sign every API response and local activation key.
    | The public key is embedded in the client SDK, the private key never leaves the server.
    */
    'signing' => [
        'path' => env('LICENSEBOX_KEY_PATH', storage_path('app/private/keys')),
        'bits' => 2048,
    ],

    'updates_disk' => env('LICENSEBOX_UPDATES_DISK', 'local'),

    /*
    | Reverse proxies / load balancers whose X-Forwarded-* headers are trusted: "*" for all, or a
    | comma-separated list of IPs/CIDRs. Without this, behind a proxy every client shares the proxy's
    | IP (rate limits, IP allow-lists and activity logs break) and signed download URLs get the wrong scheme.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
