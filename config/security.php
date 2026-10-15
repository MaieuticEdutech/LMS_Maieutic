<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (AC-35)
    |--------------------------------------------------------------------------
    |
    | Seconds a browser must refuse plain HTTP for this site after it has seen
    | the header over HTTPS. 0 (the default) sends no header at all.
    |
    | OPT-IN ON PURPOSE. Browsers remember this for the whole period, so a
    | broken or expired certificate during it locks visitors out with no
    | "continue anyway" button, and it cannot be recalled from their machines.
    | Raise it in steps, confirming HTTPS is solid at each:
    |
    |   300       5 minutes   — prove the header is sent and nothing breaks
    |   86400     1 day
    |   2592000   30 days
    |   31536000  1 year     — the usual production value
    |
    | `includeSubDomains` and `preload` are deliberately NOT sent: they would
    | commit every subdomain of the organisation's domain, and preload cannot be
    | undone in practice. Add them only as a conscious decision.
    |
    | Set SECURITY_HSTS_MAX_AGE in the deployment environment, never in code.
    |
    */

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 0),

];
