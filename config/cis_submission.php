<?php

return [
    'turnstile' => [
        'enabled' => env('CLOUDFLARE_TURNSTILE_ENABLED', true),
        'site_key' => env('CLOUDFLARE_TURNSTILE_SITE_KEY'),
        'secret_key' => env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
    ],
    'min_form_completion_seconds' => (int) env('CIS_MIN_FORM_COMPLETION_SECONDS', 3),
    'idempotency_ttl_minutes' => (int) env('CIS_SUBMISSION_TOKEN_TTL_MINUTES', 30),
    'duplicate_check_hours' => (int) env('CIS_DUPLICATE_CHECK_HOURS', 24),
    'rate_limits' => [
        'per_ip_10_minutes' => (int) env('CIS_RATE_LIMIT_PER_IP_10_MINUTES', 5),
        'per_email_60_minutes' => (int) env('CIS_RATE_LIMIT_PER_EMAIL_60_MINUTES', 10),
        'global_per_minute' => (int) env('CIS_RATE_LIMIT_GLOBAL_PER_MINUTE', 100),
        'block_minutes' => (int) env('CIS_RATE_LIMIT_BLOCK_MINUTES', 30),
        'trusted_per_ip_10_minutes' => (int) env('CIS_TRUSTED_RATE_LIMIT_PER_IP_10_MINUTES', 15),
    ],
    'attachments' => [
        'max_size_mb' => (int) env('CIS_ATTACHMENT_MAX_SIZE_MB', 10),
        'max_files' => (int) env('CIS_ATTACHMENT_MAX_FILES', 5),
        'allowed_extensions' => array_filter(array_map('trim', explode(',', (string) env('CIS_ALLOWED_ATTACHMENT_EXTENSIONS', 'pdf,png,jpg,jpeg,txt')))),
        'allow_plain_text' => env('CIS_ALLOW_TEXT_ATTACHMENTS', false),
        'antivirus_enabled' => env('CIS_ANTIVIRUS_ENABLED', false),
    ],
    'risk_thresholds' => [
        'medium' => (int) env('CIS_RISK_MEDIUM_THRESHOLD', 25),
        'high' => (int) env('CIS_RISK_HIGH_THRESHOLD', 50),
        'critical' => (int) env('CIS_RISK_CRITICAL_THRESHOLD', 80),
    ],
    'trusted_email_domains' => array_filter(array_map('trim', explode(',', (string) env('CIS_TRUSTED_EMAIL_DOMAINS', '')))),
    'trusted_networks' => array_filter(array_map('trim', explode(',', (string) env('CIS_TRUSTED_NETWORKS', '')))),
    'disposable_email_domains' => array_filter(array_map('trim', explode(',', (string) env('CIS_DISPOSABLE_EMAIL_DOMAINS', 'mailinator.com,10minutemail.com,tempmail.com')))),
];
