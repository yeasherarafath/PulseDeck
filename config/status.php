<?php

return [
    // Overridden at runtime from the admin_prefix setting (AppServiceProvider).
    'admin_prefix' => 'admin',

    // Webhook targets pass the same SSRF guard as monitored URLs. Set true only
    // when webhooks must reach private/internal hosts (e.g. an internal relay).
    'webhook_allow_private' => (bool) env('STATUS_WEBHOOK_ALLOW_PRIVATE', false),
];
