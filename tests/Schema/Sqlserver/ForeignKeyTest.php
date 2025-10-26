<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    skipIfPhp84OrHigher();
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});

describe('Foreign Keys', function () {
    it('creates tables with foreign key constraint', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on delete', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on update', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with custom reference', function () {
        schema('sqlsrv')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with various actions', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
            ;
        })->await();

        $exists = schema('sqlsrv')->hasTable('profiles')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with null on delete', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with restrict actions', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with no action', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete()->noActionOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
