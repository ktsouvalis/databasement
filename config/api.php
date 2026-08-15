<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute allowed on the v1 API, counted per access
    | token (falling back to the client IP when the request carries no token).
    | The default leaves room for busy automation while still capping a
    | runaway script. Set to 0 to disable throttling, for example when a
    | reverse proxy or gateway already enforces its own limits.
    |
    | Agent daemon routes (/api/v1/agent/*) are deliberately excluded from
    | this limit — see routes/api.php.
    |
    */

    'rate_limit' => max(0, (int) env('API_RATE_LIMIT', 300)),
];
