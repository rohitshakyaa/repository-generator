<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Repository Generator
    |--------------------------------------------------------------------------
    |
    | "path" is relative to app_path().
    | Example: 'Repositories' => app/Repositories
    |
    */

    'repositories' => [
        // Default was "Http/Repositories" in v1.x. New default keeps it inside app/.
        'path' => 'Repositories',

        // If null, it will be derived from the path (App\Repositories, App\Http\Repositories, ...)
        'namespace' => null,

        'interfaces_folder' => 'Interfaces',

        // Where bindings are added when interfaces are generated
        'provider_path' => 'Providers/RepositoryServiceProvider.php',
        'provider_class' => 'RepositoryServiceProvider',
    ],

    'services' => [
        'path' => 'Services',
        'namespace' => null,
        'interfaces_folder' => 'Interfaces',

        // Where bindings are added when service interfaces are generated
        'provider_path' => 'Providers/ServiceServiceProvider.php',
        'provider_class' => 'ServiceServiceProvider',
    ],
];
