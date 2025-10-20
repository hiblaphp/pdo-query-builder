<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Complex Foreign Key Relationships', function () {
    it('creates multiple foreign keys on single table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates self-referencing foreign key', function () {
        schema('pgsql')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        })->await();

        $exists = schema('pgsql')->hasTable('categories')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates composite foreign key', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        })->await();

        schema('pgsql')->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bio');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        })->await();

        $exists = schema('pgsql')->hasTable('user_profiles')->await();
        expect($exists)->toBeTruthy();
    });
});
