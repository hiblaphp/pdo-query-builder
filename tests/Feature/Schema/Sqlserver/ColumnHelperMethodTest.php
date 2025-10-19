<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\AsyncPDO\AsyncPDO;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
  initializeSchemaForSqlserver();
});

afterEach(function () {
   cleanupSchema();
});


describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        })->await();

        $exists = schema()->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates timestamps helper correctly', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (name) VALUES (?)',
            ['Test User']
        )->await();

        $user = AsyncPDO::fetchOne('SELECT * FROM users WHERE name = ?', ['Test User'])->await();
        
        expect($user['created_at'])->not->toBeNull();
        expect($user['updated_at'])->not->toBeNull();
    });

    it('creates softDeletes helper correctly', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});