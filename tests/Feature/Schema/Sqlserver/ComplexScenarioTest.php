<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
    SchemaTestHelper::initializeDatabase();
    SchemaTestHelper::cleanupTables(schema());
});

afterEach(function () {
    SchemaTestHelper::cleanupTables(schema());
});

describe('Complex Scenarios', function () {
    it('creates a complete blog schema', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        })->await();

        schema()->create('posts', function (Blueprint $table) {
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

        schema()->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->noActionOnDelete();
            $table->text('content');
            $table->timestamps();
        })->await();

        $usersExists = schema()->hasTable('users')->await();
        $categoriesExists = schema()->hasTable('categories')->await();
        $postsExists = schema()->hasTable('posts')->await();
        $commentsExists = schema()->hasTable('comments')->await();

        expect($usersExists)->toBeTruthy();
        expect($categoriesExists)->toBeTruthy();
        expect($postsExists)->toBeTruthy();
        expect($commentsExists)->toBeTruthy();
    });

    it('performs multiple alterations on a table', function () {
        schema()->dropIfExists('users')->await();

        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('old_email');
            $table->integer('age');
        })->await();

        schema()->table('users', function (Blueprint $table) {
            $table->renameColumn('old_email', 'email');
            $table->dropColumn('age');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique('email');
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});
