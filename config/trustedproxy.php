<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies or load balancers whose X-Forwarded-For, -Proto and -Port headers are believed, as a
    | comma-separated list of IP addresses or CIDR ranges (TRUSTED_PROXIES=10.0.0.5,10.0.1.0/24). Empty, the
    | default, trusts none: forwarded headers are ignored and the connecting address is the client. Leave it
    | empty when Nginx and PHP-FPM serve the app directly on the same server (the LIGHTSAIL.md setup); set it only
    | to the addresses of a load balancer or CDN placed in front. "*" trusts every caller and is unsafe on a
    | server that clients can reach directly.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
