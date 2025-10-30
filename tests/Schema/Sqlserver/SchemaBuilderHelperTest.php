<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    skipIfPhp84OrHigher();
    initializeSchemaForSqlserver();
});

afterEach(function () {
    cleanupSchema('sqlsrv');
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        schema('sqlsrv')->dropColumn('users', 'email')->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->renameColumn('users', 'name', 'full_name')->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->await();

        schema('sqlsrv')->dropIndex('users', 'users_email_index')->await();

        $exists = schema('sqlsrv')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema('sqlsrv')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema('sqlsrv')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        schema('sqlsrv')->dropForeign('posts', 'posts_user_id_foreign')->await();

        $exists = schema('sqlsrv')->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
