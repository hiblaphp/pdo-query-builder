<?php

use Hibla\QueryBuilder\DB;

describe('DB Facade - MySQLiAdapter', function () {
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
        
        DB::init($config, 5, 'mysqli_test');
        DB::setDefaultConnection('mysqli_test');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and((int) $result[0]['num'])->toBe(1);
    });

    it('can get first result', function () {
        $result = DB::rawFirst('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and((int) $result['num'])->toBe(1);
    });

    it('can get scalar value', function () {
        $result = DB::rawValue('SELECT 1 as num')->await();
        
        expect((int) $result)->toBe(1);
    });
});

describe('DB Facade - PdoAdapter SQLite', function () {
    beforeEach(function () {
        DB::reset();
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        DB::init($config, 5, 'sqlite_test');
        DB::setDefaultConnection('sqlite_test');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can get first result', function () {
        $result = DB::rawFirst('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result['num'])->toBe(1);
    });

    it('can get scalar value', function () {
        $result = DB::rawValue('SELECT 1 as num')->await();
        
        expect($result)->toBe(1);
    });
});

describe('DB Facade - PdoAdapter MySQL', function () {
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
        
        DB::init($config, 5, 'mysql_test');
        DB::setDefaultConnection('mysql_test');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can get first result', function () {
        $result = DB::rawFirst('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result['num'])->toBe(1);
    });

    it('can get scalar value', function () {
        $result = DB::rawValue('SELECT 1 as num')->await();
        
        expect($result)->toBe(1);
    });
});

describe('DB Facade - PdoAdapter PostgreSQL', function () {
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
        
        DB::init($config, 5, 'pgsql_test');
        DB::setDefaultConnection('pgsql_test');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can get first result', function () {
        $result = DB::rawFirst('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result['num'])->toBe(1);
    });

    it('can get scalar value', function () {
        $result = DB::rawValue('SELECT 1 as num')->await();
        
        expect($result)->toBe(1);
    });
});

describe('DB Facade - PostgresNativeAdapter', function () {
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
        
        DB::init($config, 5, 'pgsql_native_test');
        DB::setDefaultConnection('pgsql_native_test');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can run a simple SELECT query', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and((int) $result[0]['num'])->toBe(1);
    });

    it('can get first result', function () {
        $result = DB::rawFirst('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and((int) $result['num'])->toBe(1);
    });

    it('can get scalar value', function () {
        $result = DB::rawValue('SELECT 1 as num')->await();
        
        expect((int) $result)->toBe(1);
    });
});

describe('DB Facade - Multiple Connections', function () {
    beforeEach(function () {
        DB::reset();
        
        DB::initMultiple([
            'mysql_conn' => [
                'config' => [
                    'driver' => 'mysql',
                    'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
                    'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
                    'database' => getenv('MYSQL_DATABASE') ?: 'test_db',
                    'username' => getenv('MYSQL_USERNAME') ?: 'test_user',
                    'password' => getenv('MYSQL_PASSWORD') ?: 'test_password',
                ],
                'pool_size' => 5,
            ],
            'sqlite_conn' => [
                'config' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
                'pool_size' => 3,
            ],
        ], 'mysql_conn');
    });

    afterEach(function () {
        DB::reset();
    });

    it('can use default connection', function () {
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can use named connection', function () {
        $result = DB::connection('sqlite_conn')->raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can switch default connection', function () {
        DB::setDefaultConnection('sqlite_conn');
        
        expect(DB::getDefaultConnection())->toBe('sqlite_conn');
        
        $result = DB::raw('SELECT 1 as num')->await();
        
        expect($result)->toBeArray()
            ->and($result[0]['num'])->toBe(1);
    });

    it('can check connection existence', function () {
        expect(DB::hasConnection('mysql_conn'))->toBeTrue()
            ->and(DB::hasConnection('sqlite_conn'))->toBeTrue()
            ->and(DB::hasConnection('nonexistent'))->toBeFalse();
    });

    it('can get connection names', function () {
        $names = DB::getConnectionNames();
        
        expect($names)->toBeArray()
            ->and($names)->toContain('mysql_conn', 'sqlite_conn');
    });

    it('can remove connection', function () {
        DB::removeConnection('sqlite_conn');
        
        expect(DB::hasConnection('sqlite_conn'))->toBeFalse();
    });
});