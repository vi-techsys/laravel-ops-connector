<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Connector API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate requests from Laravel Ops.
    |
    */
    'api_key' => env('CONNECTOR_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Connector API Secret
    |--------------------------------------------------------------------------
    |
    | The API secret used to sign and verify requests.
    |
    */
    'api_secret' => env('CONNECTOR_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Timestamp Tolerance
    |--------------------------------------------------------------------------
    |
    | The number of seconds a request timestamp can be from the current time.
    | This prevents replay attacks. Default: 300 seconds (5 minutes).
    |
    */
    'timestamp_tolerance' => env('CONNECTOR_TIMESTAMP_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Nonce Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long to cache nonces to prevent replay attacks (in seconds).
    | Should match or exceed the timestamp tolerance.
    |
    */
    'nonce_cache_duration' => env('CONNECTOR_NONCE_CACHE_DURATION', 300),

    /*
    |--------------------------------------------------------------------------
    | Allowed Operations
    |--------------------------------------------------------------------------
    |
    | List of Artisan operations that can be executed remotely.
    | Only these operations will be available to Laravel Ops.
    |
    */
    'allowed_operations' => [
        // Cache Operations
        'cache:clear',
        'cache:forget',
        'config:clear',
        'route:clear',
        'view:clear',
        'event:clear',
        'optimize:clear',
        'optimize',
        
        // Database Operations
        'migrate:status',
        'migrate',
        'migrate:rollback',
        'migrate:refresh',
        'migrate:reset',
        'db:show',
        'db:table',
        'db:wipe',
        
        // Queue Operations
        'queue:clear',
        'queue:failed',
        'queue:flush',
        'queue:forget',
        'queue:monitor',
        'queue:restart',
        'queue:retry',
        'queue:retry-batch',
        'queue:work',
        
        // Schedule Operations
        'schedule:list',
        'schedule:run',
        'schedule:test',
        'schedule:work',
        
        // Storage Operations
        'storage:link',
        
        // Application Operations
        'down',
        'up',
        'about',
        'env',
        'key:generate',
        
        // Package Operations
        'package:discover',
        
        // Auth Operations
        'auth:clear-resets',
        
        // Permission Operations
        'permission:cache-reset',
        
        // Diagnostic Operations
        'route:list',
        'env',
        'about',
        
        // Log Operations
        'log:clear',
        'log:view',
        'log:statistics',
        
        // Queue Monitoring Operations
        'queue:inspect',
        'queue:failed-details',
        'queue:retry-all',
        'queue:purge',
        
        // Scheduler Monitoring Operations
        'schedule:inspect',
        'schedule:history',
        'schedule:run-task',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Operations
    |--------------------------------------------------------------------------
    |
    | Whether to allow remote operation execution. Set to false for
    | read-only monitoring without execution capabilities.
    |
    */
    'enable_operations' => env('CONNECTOR_ENABLE_OPERATIONS', true),
];
