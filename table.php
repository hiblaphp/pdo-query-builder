<?php

use Hibla\PdoQueryBuilder\DB;

require 'vendor/autoload.php';

// $results = DB::table('users')->toObject()->first()->await();
// $results2 = DB::connection('postgres_backup')->table('users')->toObject()->first()->await();

DB::init([
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'aladyn_api',
    'username' => 'postgres',
    'password' => 'root',
]);

$results = DB::table('users')->toObject()->first()->await();
print_r($results);
// print_r($results2);
