<?php

use Hibla\PdoQueryBuilder\DB;

use function Hibla\await;

require 'vendor/autoload.php';

await(DB::table('users')->get());
await(DB::table('users')->get());