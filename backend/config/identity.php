<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Identity Verification Provider
    |--------------------------------------------------------------------------
    |
    | The provider key resolved by IdentityProviderFactory. "mock" ships a
    | deterministic in-memory provider for development and tests. "nida"
    | is a placeholder wired for the official NIDA integration once
    | credentials and documentation are available — see
    | App\Services\Identity\Providers\OfficialNidaProvider.
    |
    */
    'provider' => env('IDENTITY_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],

        'nida' => [
            'driver' => 'nida',
            'base_url' => env('NIDA_BASE_URL'),
            'client_id' => env('NIDA_CLIENT_ID'),
            'client_secret' => env('NIDA_CLIENT_SECRET'),
            'certificate_path' => env('NIDA_CERTIFICATE_PATH'),
            'timeout' => env('NIDA_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Maximum verification attempts a single user may submit within the
    | window (minutes), enforced by IdentityVerificationService before a
    | provider call is made.
    |
    */
    'rate_limit' => [
        'max_attempts' => env('IDENTITY_RATE_LIMIT_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('IDENTITY_RATE_LIMIT_DECAY_MINUTES', 10),
    ],
];
