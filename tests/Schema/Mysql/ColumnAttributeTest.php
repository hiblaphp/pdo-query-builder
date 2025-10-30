<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Column Attributes', function () {
    it('creates columns with nullable attribute', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable(false);
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with default values', function () {
        schema()->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->default('default_value');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(true);
            $table->decimal('amount', 10, 2)->default(0.00);
        })->await();

        $exists = schema()->hasTable('settings')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with unsigned attribute', function () {
        schema()->create('counters', function (Blueprint $table) {
            $table->id();
            $table->integer('count')->unsigned();
            $table->bigInteger('big_count')->unsigned();
        })->await();

        $exists = schema()->hasTable('counters')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates columns with useCurrent for timestamps', function () {
        schema()->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
        })->await();

        $exists = schema()->hasTable('logs')->await();
        expect($exists)->toBeTruthy();
    });
});
