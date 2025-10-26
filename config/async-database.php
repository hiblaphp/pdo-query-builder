<?php

use function Rcalicdan\ConfigLoader\env;

require 'vendor/autoload.php';

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default database connection that will be used
    | by your application. The value should match one of the connection names
    | defined in the 'connections' array below.
    |
    */
    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are the database connections configured for your application.
    | Each connection can be customized with specific driver options and
    | PDO attributes to control behavior like error handling and fetch modes.
    | You can add more connections as needed for your application.
    |
    | Supported drivers: "sqlite", "mysql", "pgsql", "pgsql_native", "sqlsrv"
    |
    */
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => match ($path = env('DB_SQLITE_PATH', null)) {
                ':memory:' => 'file::memory:?cache=shared',
                null => __DIR__ . '/../database/database.sqlite',
                default => $path,
            },
            'pool_size' => env('DB_POOL_SIZE', 10, true),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306, true),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'pool_size' => env('DB_POOL_SIZE', 10, true),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432, true),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'pool_size' => env('DB_POOL_SIZE', 10, true),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
            ],
        ],

        'pgsql_native' => [
            'driver' => 'pgsql_native',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432, true),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'pool_size' => env('DB_POOL_SIZE', 10, true),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 1433),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'pool_size' => env('DB_POOL_SIZE', 10, true),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Templates
    |--------------------------------------------------------------------------
    |
    | Configure where pagination templates should be published and loaded from.
    |
    | - 'templates_path': The directory where templates will be published and loaded.
    |                     Set to null to use the default built-in templates.
    | - 'default_template': The default pagination template to use.
    | - 'default_cursor_template': The default cursor pagination template to use.
    |
    | To publish templates, run: php async-pdo publish:templates
    | The templates will be copied to the path specified below.
    |
    */
    'pagination' => [
        'templates_path' => null,
        'default_template' => 'tailwind',
        'default_cursor_template' => 'cursor-tailwind',
    ],
];
