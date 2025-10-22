<?php

use Hibla\AsyncPDO\AsyncPDO;
use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates timestamps helper correctly', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        })->await();

        \Hibla\PdoQueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['Test User']
        )->await();

        $user = \Hibla\PdoQueryBuilder\DB::rawFirst('SELECT * FROM users WHERE name = ?', ['Test User'])->await();

        expect($user['created_at'])->not->toBeNull();
        expect($user['updated_at'])->not->toBeNull();
    });

    it('creates softDeletes helper correctly', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
