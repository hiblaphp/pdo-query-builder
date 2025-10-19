<?php

$host = '127.0.0.1';
$port = 5432;
$database = 'horses';
$username = 'postgres';
$password = 'root';

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ Successfully connected to PostgreSQL!\n";
    
    // Test query
    $result = $pdo->query("SELECT version();")->fetch();
    echo "✓ PostgreSQL Version: " . $result['version'] . "\n";
    
    // Check if test_db exists
    $result = $pdo->query("SELECT datname FROM pg_database WHERE datname = 'test_db';")->fetch();
    if ($result) {
        echo "✓ Database 'test_db' exists\n";
    } else {
        echo "✗ Database 'test_db' does not exist\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
}