<?php

return [
    'email_password_enabled' => filter_var(env('AUTH_EMAIL_PASSWORD_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'providers' => [
        'google' => [
            'issuer' => ['https://accounts.google.com', 'accounts.google.com'],
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
            'client_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('GOOGLE_OAUTH_CLIENT_IDS', env('GOOGLE_CLIENT_ID', '')))
            ))),
        ],

        'apple' => [
            'issuer' => ['https://appleid.apple.com'],
            'jwks_uri' => 'https://appleid.apple.com/auth/keys',
            'client_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('APPLE_OAUTH_CLIENT_IDS', env('APPLE_CLIENT_ID', '')))
            ))),
        ],
    ],
];
