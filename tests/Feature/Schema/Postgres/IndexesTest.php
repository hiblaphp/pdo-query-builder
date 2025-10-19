<?php

use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});


describe('Indexes', function () {
    it('creates a table with primary key', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with unique index', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with regular index', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with composite index', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with fulltext index', function () {
        schema('pgsql')->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->await();

        $exists = schema('pgsql')->hasTable('articles')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates table with various spatial types', function () {
        try {
            DB::rawExecute('CREATE EXTENSION IF NOT EXISTS postgis', [])->await();
        } catch (\Exception $e) {
            $this->markTestSkipped('PostGIS extension not available');
        }

        schema('pgsql')->create('geo_test', function (Blueprint $table) {
            $table->id();
            $table->point('location')->spatialIndex();
            $table->lineString('route')->nullable();
            $table->polygon('area')->spatialIndex();
            $table->geometry('shape')->nullable();
        })->await();

        $exists = schema('pgsql')->hasTable('geo_test')->await();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('geo_test')->await();
    });

    it('creates table with SRID specification', function () {
        try {
            DB::rawExecute('CREATE EXTENSION IF NOT EXISTS postgis', [])->await();
        } catch (\Exception $e) {
            $this->markTestSkipped('PostGIS extension not available');
        }

        schema('pgsql')->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->point('location');
            $table->spatialIndex('location');
        })->await();

        $exists = schema('pgsql')->hasTable('stores')->await();
        expect($exists)->toBeTruthy();

        schema('pgsql')->dropIfExists('stores')->await();
    });

    it('creates a table with named indexes', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with index algorithms', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
