<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Advanced Index Scenarios', function () {
    it('creates multiple indexes on same column', function () {
        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('content');
            $table->index('title', 'custom_title_index');
        })->await();

        $exists = schema()->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates composite unique index', function () {
        schema()->create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        })->await();

        $exists = schema()->hasTable('user_roles')->await();
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('user_roles')->await();
    });

    it('creates indexes with custom names', function () {
        schema()->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index('idx_product_sku');
            $table->string('name')->unique('unq_product_name');
        })->await();

        $exists = schema()->hasTable('products')->await();
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('products')->await();
    });
});
