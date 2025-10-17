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

describe('Table Creation', function () {
    it('creates a basic table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with various column types', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with auto-increment columns', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->increments('legacy_id');
            $table->bigIncrements('big_id');
            $table->smallIncrements('small_id');
            $table->string('name');
        })->await();

        $exists = $this->schema->hasTable('categories')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with integer variations', function () {
        $this->schema->create('stats', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('tiny_num');
            $table->smallInteger('small_num');
            $table->mediumInteger('medium_num');
            $table->integer('regular_num');
            $table->bigInteger('big_num');
            $table->unsignedTinyInteger('unsigned_tiny');
            $table->unsignedBigInteger('unsigned_big');
        })->await();

        $exists = $this->schema->hasTable('stats')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with text variations', function () {
        $this->schema->create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('short_text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->string('title', 100);
        })->await();

        $exists = $this->schema->hasTable('documents')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with decimal variations', function () {
        $this->schema->create('financials', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->float('rate', 5, 2);
            $table->double('precise_value', 15, 8);
            $table->unsignedDecimal('positive_amount', 8, 2);
        })->await();

        $exists = $this->schema->hasTable('financials')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with date/time columns', function () {
        $this->schema->create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->dateTime('event_datetime');
            $table->timestamp('event_timestamp');
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('events')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with enum column', function () {
        $this->schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('orders')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with soft deletes', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with comments', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User full name');
            $table->string('email')->comment('User email address')->unique();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with after positioning', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->after('name');
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});