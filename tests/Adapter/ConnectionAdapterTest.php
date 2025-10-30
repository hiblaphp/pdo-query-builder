<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Adapters\MySQLiAdapter;
use Hibla\QueryBuilder\Adapters\PdoAdapter;
use Hibla\QueryBuilder\Adapters\PostgresNativeAdapter;
use Hibla\QueryBuilder\DB;

function adapter($adapter = null)
{
    static $instance;
    if ($adapter !== null) {
        $instance = $adapter;
    }

    return $instance;
}

describe('MySQLiAdapter', function () {
    beforeEach(function () {
        DB::reset();

        $config = [
            'driver' => 'mysqli',
            'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
            'database' => getenv('MYSQL_DATABASE') ?: 'test_db',
            'username' => getenv('MYSQL_USERNAME') ?: 'test_user',
            'password' => getenv('MYSQL_PASSWORD') ?: 'test_password',
        ];

        adapter(new MySQLiAdapter($config, 5));
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = adapter()->query('SELECT 1 as num')->await();

        expect($result)->toBeArray()
            ->and((int) $result[0]['num'])->toBe(1)
        ;
    });

    it('returns correct driver name', function () {
        expect(adapter()->getDriver())->toBe('mysqli_native');
    });
});

describe('PdoAdapter - SQLite', function () {
    beforeEach(function () {
        DB::reset();

        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];

        adapter(new PdoAdapter($config, 5));
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = adapter()->query('SELECT 1 as num')->await();

        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1)
        ;
    });

    it('returns correct driver name', function () {
        expect(adapter()->getDriver())->toBe('sqlite');
    });
});

describe('PdoAdapter - MySQL', function () {
    beforeEach(function () {
        DB::reset();

        $config = [
            'driver' => 'mysql',
            'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
            'database' => getenv('MYSQL_DATABASE') ?: 'test_db',
            'username' => getenv('MYSQL_USERNAME') ?: 'test_user',
            'password' => getenv('MYSQL_PASSWORD') ?: 'test_password',
        ];

        adapter(new PdoAdapter($config, 5));
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = adapter()->query('SELECT 1 as num')->await();

        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1)
        ;
    });

    it('returns correct driver name', function () {
        expect(adapter()->getDriver())->toBe('mysql');
    });
});

describe('PdoAdapter - PostgreSQL', function () {
    beforeEach(function () {
        DB::reset();

        $config = [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('PGSQL_PORT') ?: 5432),
            'database' => getenv('PGSQL_DATABASE') ?: 'test_db',
            'username' => getenv('PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('PGSQL_PASSWORD') ?: 'postgres',
        ];

        adapter(new PdoAdapter($config, 5));
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = adapter()->query('SELECT 1 as num')->await();

        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1)
        ;
    });

    it('returns correct driver name', function () {
        expect(adapter()->getDriver())->toBe('pgsql');
    });
});

describe('PostgresNativeAdapter', function () {
    beforeEach(function () {
        DB::reset();

        $config = [
            'driver' => 'pgsql_native',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('PGSQL_PORT') ?: 5432),
            'database' => getenv('PGSQL_DATABASE') ?: 'test_db',
            'username' => getenv('PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('PGSQL_PASSWORD') ?: 'postgres',
        ];

        adapter(new PostgresNativeAdapter($config, 5));
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = adapter()->query('SELECT 1 as num')->await();

        expect($result)->toBeArray()
            ->and((int) $result[0]['num'])->toBe(1)
        ;
    });

    it('returns correct driver name', function () {
        expect(adapter()->getDriver())->toBe('pgsql_native');
    });
});
