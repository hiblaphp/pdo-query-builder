<?php

use Hibla\PdoQueryBuilder\DB;

require 'vendor/autoload.php';

DB::init([
    'driver' => 'sqlite',
    'database' => 'file::memory:?cache=shared',
]);


DB::rawExecute('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, username TEXT UNIQUE)')->await();
DB::rawExecute('INSERT INTO users (name, email, username) VALUES (?, ?, ?)', ['John Doe', 'john@example.com', 'johndoe'])->await();

$result = DB::raw('SELECT * FROM users WHERE username = ?', ['johndoe'])->await();

print_r($result);
