<?php

use Hibla\PdoQueryBuilder\DB;

require 'vendor/autoload.php';

$results = DB::table('users')->get()->await();

print_r($results);
