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

describe('Column Attributes', function () {
    it('creates columns with nullable attribute', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable(false);
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with default values', function () {
        $this->schema->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->default('default_value');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(true);
            $table->decimal('amount', 10, 2)->default(0.00);
        })->await();

        $exists = $this->schema->hasTable('settings')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with unsigned attribute', function () {
        $this->schema->create('counters', function (Blueprint $table) {
            $table->id();
            $table->integer('count')->unsigned();
            $table->bigInteger('big_count')->unsigned();
        })->await();

        $exists = $this->schema->hasTable('counters')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with useCurrent for timestamps', function () {
        $this->schema->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
        })->await();

        $exists = $this->schema->hasTable('logs')->await();
        expect($exists)->toBeTrue();
    });
});