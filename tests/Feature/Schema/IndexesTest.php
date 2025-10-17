<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    SchemaTestHelper::initializeDatabase();
    $this->schema = SchemaTestHelper::createSchemaBuilder();
    SchemaTestHelper::cleanupTables($this->schema);
});

afterEach(function () {
    SchemaTestHelper::cleanupTables($this->schema);
});

describe('Indexes', function () {
    it('creates a table with primary key', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with unique index', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with regular index', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with composite index', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with fulltext index', function () {
        $this->schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->await();

        $exists = $this->schema->hasTable('articles')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with spatial index', function () {
        $this->schema->create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('coordinates')->spatialIndex();
        })->await();

        $exists = $this->schema->hasTable('locations')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with named indexes', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with index algorithms', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});