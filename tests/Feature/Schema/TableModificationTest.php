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

describe('Table Modification', function () {
    it('adds columns to existing table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->integer('age')->default(0);
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('drops columns from table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'age']);
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('drops single column from table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->dropColumn('email');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('renames a column', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('modifies column type', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->modifyString('name', 200)->nullable();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('modifies integer column', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('age');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->modifyInteger('age', true)->nullable();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('modifies various column types', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('count');
            $table->text('bio');
            $table->boolean('active');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->modifyBigInteger('count', true);
            $table->modifyText('bio');
            $table->modifyBoolean('active');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('adds index to existing table', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
        })->await();

        $this->schema->table('posts', function (Blueprint $table) {
            $table->index('slug');
            $table->unique('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('drops index from table', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
        })->await();

        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_title_index');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('drops unique index from table', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
        })->await();

        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropUnique('posts_slug_unique');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('drops primary key from table', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->string('slug');
            $table->primary('slug');
        })->await();

        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropPrimary();
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('drops foreign key from table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropForeign('posts_user_id_foreign');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});