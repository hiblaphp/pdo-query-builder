<?php

use Hibla\PdoQueryBuilder\DB;

require 'vendor/autoload.php';

DB::init([
    "driver" => "pgsql_native",
    "host" => "localhost",
    "port" => 5432,
    "database" => "aladyn_api",
    "username" => "postgres",
    "password" => "root",
]);


$results = DB::raw("SELECT * FROM users where id = $1", [1])->await();

var_dump($results);