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

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        $this->schema->dropColumn('users', 'email')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses renameColumn helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->renameColumn('users', 'name', 'full_name')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses dropIndex helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->await();

        $this->schema->dropIndex('users', 'users_email_index')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses dropForeign helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $this->schema->dropForeign('posts', 'posts_user_id_foreign')->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});