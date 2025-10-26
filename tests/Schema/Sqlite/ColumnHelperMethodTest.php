<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        })->await();

        $exists = schema('sqlite')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates timestamps helper correctly', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['Test User']
        )->await();

        $user = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM users WHERE name = ?', ['Test User'])->await();

        expect($user['created_at'])->not->toBeNull();
        expect($user['updated_at'])->not->toBeNull();
    });

    it('creates softDeletes helper correctly', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
