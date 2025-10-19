<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});


describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        schema('sqlite')->dropColumn('users', 'email')->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->renameColumn('users', 'name', 'full_name')->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->await();

        schema('sqlite')->dropIndex('users', 'users_email_index')->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        schema('sqlite')->dropForeign('posts', 'posts_user_id_foreign')->await();

        $exists = schema('sqlite')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
