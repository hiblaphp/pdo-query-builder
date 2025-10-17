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

describe('Table Operations', function () {
    it('drops a table', function () {
        $this->schema->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->drop('temp_table')->await();

        $exists = $this->schema->hasTable('temp_table')->await();
        expect($exists)->toBeFalse();
    });

    it('drops a table if exists', function () {
        $this->schema->dropIfExists('nonexistent_table')->await();

        $exists = $this->schema->hasTable('nonexistent_table')->await();
        expect($exists)->toBeFalse();
    });

    it('renames a table', function () {
        $this->schema->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->rename('old_name', 'new_name')->await();

        $oldExists = $this->schema->hasTable('old_name')->await();
        $newExists = $this->schema->hasTable('new_name')->await();

        expect($oldExists)->toBeFalse();
        expect($newExists)->toBeTrue();

        $this->schema->dropIfExists('new_name')->await();
    });

    it('checks if table exists', function () {
        $exists = $this->schema->hasTable('nonexistent')->await();
        expect($exists)->toBeFalse();

        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});