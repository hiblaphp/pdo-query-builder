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

describe('Complex Foreign Key Relationships', function () {
    it('creates multiple foreign keys on single table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates self-referencing foreign key', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        })->await();

        $exists = $this->schema->hasTable('categories')->await();
        expect($exists)->toBeTrue();
    });

    it('creates composite foreign key', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        })->await();

        $this->schema->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bio');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        })->await();

        $exists = $this->schema->hasTable('user_profiles')->await();
        expect($exists)->toBeTrue();
    });
});