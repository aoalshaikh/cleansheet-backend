<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | This is the model used for managing tenants in your application.
    |
    */
    'tenant_model' => App\Models\Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the model used for managing users in your application.
    |
    */
    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | These are the default settings applied to new tenants.
    |
    */
    'default_settings' => [
        'features' => [
            'dashboard' => true,
            'api_access' => true,
            'file_uploads' => true,
            'team_management' => false,
            'advanced_reporting' => false,
        ],
        'capabilities' => [
            'max_users' => 5,
            'max_storage' => '1GB',
            'max_projects' => 10,
            'api_rate_limit' => 1000,
        ],
        'subscription' => [
            'plan' => 'basic',
            'status' => 'active',
            'trial_days' => 14,
        ],
        'branding' => [
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'logo_url' => null,
        ],
        'notifications' => [
            'email' => true,
            'slack' => false,
            'webhook' => false,
        ],
        'security' => [
            'two_factor' => false,
            'ip_whitelist' => [],
            'password_policy' => 'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans Configuration
    |--------------------------------------------------------------------------
    |
    | Define the available plans and their features.
    |
    */
    'plans' => [
        'basic' => [
            'features' => [
                'dashboard' => true,
                'api_access' => true,
                'file_uploads' => true,
                'team_management' => false,
                'advanced_reporting' => false,
            ],
            'capabilities' => [
                'max_users' => 5,
                'max_storage' => '1GB',
                'max_projects' => 10,
                'api_rate_limit' => 1000,
            ],
        ],
        'premium' => [
            'features' => [
                'dashboard' => true,
                'api_access' => true,
                'file_uploads' => true,
                'team_management' => true,
                'advanced_reporting' => true,
            ],
            'capabilities' => [
                'max_users' => 25,
                'max_storage' => '10GB',
                'max_projects' => 50,
                'api_rate_limit' => 5000,
            ],
        ],
        'enterprise' => [
            'features' => [
                'dashboard' => true,
                'api_access' => true,
                'file_uploads' => true,
                'team_management' => true,
                'advanced_reporting' => true,
                'custom_domain' => true,
                'sso' => true,
            ],
            'capabilities' => [
                'max_users' => null, // unlimited
                'max_storage' => null, // unlimited
                'max_projects' => null, // unlimited
                'api_rate_limit' => null, // unlimited
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant domains are handled.
    |
    */
    'domain' => [
        'subdomain' => [
            'enabled' => true,
            'suffix' => env('APP_DOMAIN', 'localhost'),
        ],
        'custom_domains' => [
            'enabled' => true,
            'verify_ssl' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant data is stored.
    |
    */
    'database' => [
        'tenant_aware_models' => [
            // List of models that should be tenant-aware
            App\Models\User::class,
            // Add other models here
        ],
        'activity_logging' => [
            'enabled' => true,
            'tenant_column' => 'tenant_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific caching.
    |
    */
    'cache' => [
        'prefix' => 'tenant',
        'ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific routing.
    |
    */
    'routes' => [
        'prefix' => 'tenant',
        'middleware' => ['web', 'auth', 'tenant'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific security settings.
    |
    */
    'security' => [
        'password_policies' => [
            'default' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_numeric' => true,
                'require_special_char' => false,
            ],
            'strict' => [
                'min_length' => 12,
                'require_uppercase' => true,
                'require_numeric' => true,
                'require_special_char' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Configure which features are available in the application.
    |
    */
    'features' => [
        'tenant_impersonation' => false,
        'tenant_switching' => true,
        'tenant_deletion' => true,
        'tenant_backup' => true,
    ],
];
