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

describe('Advanced Index Scenarios', function () {
    it('creates multiple indexes on same column', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('content');
            $table->index('title', 'custom_title_index');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates composite unique index', function () {
        $this->schema->create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        })->await();

        $exists = $this->schema->hasTable('user_roles')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('user_roles')->await();
    });

    it('creates indexes with custom names', function () {
        $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index('idx_product_sku');
            $table->string('name')->unique('unq_product_name');
        })->await();

        $exists = $this->schema->hasTable('products')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('products')->await();
    });
});