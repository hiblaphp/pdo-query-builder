<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Table Creation', function () {
    it('creates a basic table', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with various column types', function () {
        schema()->create('posts', function (Blueprint $table) {
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

        $exists = schema()->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with auto-increment columns', function () {
        schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->increments('legacy_id');
            $table->bigIncrements('big_id');
            $table->smallIncrements('small_id');
            $table->string('name');
        })->await();

        $exists = schema()->hasTable('categories')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with integer variations', function () {
        schema()->create('stats', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('tiny_num');
            $table->smallInteger('small_num');
            $table->mediumInteger('medium_num');
            $table->integer('regular_num');
            $table->bigInteger('big_num');
            $table->unsignedTinyInteger('unsigned_tiny');
            $table->unsignedBigInteger('unsigned_big');
        })->await();

        $exists = schema()->hasTable('stats')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with text variations', function () {
        schema()->create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('short_text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->string('title', 100);
        })->await();

        $exists = schema()->hasTable('documents')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with decimal variations', function () {
        schema()->create('financials', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->float('rate', 5, 2);
            $table->double('precise_value', 15, 8);
            $table->unsignedDecimal('positive_amount', 8, 2);
        })->await();

        $exists = schema()->hasTable('financials')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with date/time columns', function () {
        schema()->create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->dateTime('event_datetime');
            $table->timestamp('event_timestamp');
            $table->timestamps();
        })->await();

        $exists = schema()->hasTable('events')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with enum column', function () {
        schema()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
            $table->timestamps();
        })->await();

        $exists = schema()->hasTable('orders')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with soft deletes', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with comments', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User full name');
            $table->string('email')->comment('User email address')->unique();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates a table with after positioning', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->after('name');
            $table->timestamps();
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});