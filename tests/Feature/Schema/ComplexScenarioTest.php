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

describe('Complex Scenarios', function () {
    it('creates a complete blog schema', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
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

        $this->schema->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        })->await();

        $usersExists = $this->schema->hasTable('users')->await();
        $categoriesExists = $this->schema->hasTable('categories')->await();
        $postsExists = $this->schema->hasTable('posts')->await();
        $commentsExists = $this->schema->hasTable('comments')->await();

        expect($usersExists)->toBeTrue();
        expect($categoriesExists)->toBeTrue();
        expect($postsExists)->toBeTrue();
        expect($commentsExists)->toBeTrue();
    });

    it('performs multiple alterations on a table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('old_email');
            $table->integer('age');
        })->await();

        $this->schema->table('users', function (Blueprint $table) {
            $table->renameColumn('old_email', 'email');
            $table->dropColumn('age');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique('email');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});