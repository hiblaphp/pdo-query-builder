<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});


describe('Indexes', function () {
    it('creates a table with primary key', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $exists =    schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with unique index', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->await();

        $exists =    schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with regular index', function () {
        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->await();

        $exists =    schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with composite index', function () {
        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->await();

        $exists =    schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with fulltext index', function () {
        schema('sqlsrv')->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->await();

        $exists =    schema('sqlsrv')->hasTable('articles')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates table with various spatial types', function () {
        schema('sqlsrv')->create('geo_test', function (Blueprint $table) {
            $table->id();
            $table->point('location')->spatialIndex();
            $table->lineString('route')->nullable();
            $table->polygon('area')->spatialIndex();
            $table->geometry('shape')->nullable();
        })->await();

        $exists =    schema('sqlsrv')->hasTable('geo_test')->await();
        expect($exists)->toBeTruthy();

        schema('sqlsrv')->dropIfExists('geo_test')->await();
    });

    it('creates table with SRID specification', function () {
        schema('sqlsrv')->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->point('location');
            $table->spatialIndex('location');
        })->await();



        $exists =    schema('sqlsrv')->hasTable('stores')->await();
        expect($exists)->toBeTruthy();

        schema('sqlsrv')->dropIfExists('stores')->await();
    });

    it('creates a table with named indexes', function () {
        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->await();

        $exists =    schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with index algorithms', function () {
        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->await();

        $exists =    schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
