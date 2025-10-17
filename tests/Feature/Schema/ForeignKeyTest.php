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

describe('Foreign Keys', function () {
    it('creates tables with foreign key constraint', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with cascade on delete', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with cascade on update', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with custom reference', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with various actions', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        })->await();

        $exists = $this->schema->hasTable('profiles')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with null on delete', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with restrict actions', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with no action', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete()->noActionOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});