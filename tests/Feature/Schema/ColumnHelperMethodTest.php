<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\AsyncPDO\AsyncPDO;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    SchemaTestHelper::initializeDatabase();
    $this->schema = SchemaTestHelper::createSchemaBuilder();
    SchemaTestHelper::cleanupTables($this->schema);
});

afterEach(function () {
    SchemaTestHelper::cleanupTables($this->schema);
});

describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates timestamps helper correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
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
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});