<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});

describe('Advanced Index Scenarios', function () {
    it('creates multiple indexes on same column', function () {
        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('content');
            $table->index('title', 'custom_title_index');
        })->await();

        $exists = schema('sqlite')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates composite unique index', function () {
        schema('sqlite')->create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        })->await();

        $exists = schema('sqlite')->hasTable('user_roles')->await();
        expect($exists)->toBeTruthy();

        schema('sqlite')->dropIfExists('user_roles')->await();
    });

    it('creates indexes with custom names', function () {
        schema('sqlite')->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index('idx_product_sku');
            $table->string('name')->unique('unq_product_name');
        })->await();

        $exists = schema('sqlite')->hasTable('products')->await();
        expect($exists)->toBeTruthy();

        schema('sqlite')->dropIfExists('products')->await();
    });
});
