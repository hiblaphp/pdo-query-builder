<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForSqlite();
});

afterEach(function () {
    cleanupSchema('sqlite');
});


describe('Complex Scenarios', function () {
    it('creates a complete blog schema', function () {
        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        schema('sqlite')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        })->await();

        schema('sqlite')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_published']);
            $table->fullText(['title', 'content']);
        })->await();

        schema('sqlite')->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        })->await();

        $usersExists = schema('sqlite')->hasTable('users')->await();
        $categoriesExists = schema('sqlite')->hasTable('categories')->await();
        $postsExists = schema('sqlite')->hasTable('posts')->await();
        $commentsExists = schema('sqlite')->hasTable('comments')->await();

        expect($usersExists)->toBeTruthy();
        expect($categoriesExists)->toBeTruthy();
        expect($postsExists)->toBeTruthy();
        expect($commentsExists)->toBeTruthy();
    });

    it('performs multiple alterations on a table', function () {
        schema('sqlite')->dropIfExists('users')->await();

        schema('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('old_email');
            $table->integer('age');
        })->await();

        schema('sqlite')->table('users', function (Blueprint $table) {
            $table->renameColumn('old_email', 'email');
            $table->dropColumn('age');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique('email');
        })->await();

        $exists = schema('sqlite')->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
