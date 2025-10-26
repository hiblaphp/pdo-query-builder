<?php

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForPostgres();
});

afterEach(function () {
    cleanupSchema('pgsql');
});

describe('Table Modification', function () {
    it('adds columns to existing table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->integer('age')->default(0);
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops columns from table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'age']);
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops single column from table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->dropColumn('email');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('renames a column', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('modifies column type', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->modifyString('name', 200)->nullable();
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('modifies integer column', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('age');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->modifyInteger('age', true)->nullable();
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('modifies various column types', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('count');
            $table->text('bio');
            $table->boolean('active');
        })->await();

        schema('pgsql')->table('users', function (Blueprint $table) {
            $table->modifyBigInteger('count', true);
            $table->modifyText('bio');
            $table->modifyBoolean('active');
        })->await();

        $exists = schema('pgsql')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('adds index to existing table', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
        })->await();

        schema('pgsql')->table('posts', function (Blueprint $table) {
            $table->index('slug');
            $table->unique('title');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops index from table', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
        })->await();

        schema('pgsql')->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_title_index');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops unique index from table', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
        })->await();

        schema('pgsql')->table('posts', function (Blueprint $table) {
            $table->dropUnique('posts_slug_unique');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops primary key from table', function () {
        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->string('slug');
            $table->primary('slug');
        })->await();

        schema('pgsql')->table('posts', function (Blueprint $table) {
            $table->dropPrimary();
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });

    it('drops foreign key from table', function () {
        schema('pgsql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('pgsql')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        schema('pgsql')->table('posts', function (Blueprint $table) {
            $table->dropForeign('posts_user_id_foreign');
        })->await();

        $exists = schema('pgsql')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
