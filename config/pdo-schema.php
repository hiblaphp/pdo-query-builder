<?php

require 'vendor/autoload.php';

use function Rcalicdan\ConfigLoader\env;

return [
    'migrations_path' => __DIR__ . '/../database/migrations',
    'migrations_table' => 'migrations',
    'naming_convention' => 'timestamp',
    'timezone' => env('TIMEZONE', 'UTC'),
];
