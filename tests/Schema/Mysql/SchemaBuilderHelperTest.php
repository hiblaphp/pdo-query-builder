<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        schema()->dropColumn('users', 'email')->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->renameColumn('users', 'name', 'full_name')->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->await();

        schema()->dropIndex('users', 'users_email_index')->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        schema()->dropForeign('posts', 'posts_user_id_foreign')->await();

        $exists = schema()->hasTable('posts')->await();
        expect($exists)->toBeTruthy();
    });
});
