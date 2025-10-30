<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Foreign Keys', function () {
    it('creates tables with foreign key constraint', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on delete', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with cascade on update', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with custom reference', function () {
        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with various actions', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
            ;
        })->await();

        $exists = schema('pgsql')->hasTable('profiles')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with null on delete', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with restrict actions', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates foreign key with no action', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete()->noActionOnUpdate();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
