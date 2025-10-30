<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\Blueprint;

beforeEach(function () {
    initializeSchemaForMysql();
});

afterEach(function () {
    cleanupSchema();
});

describe('Data Insertion and Verification', function () {
    it('creates table and inserts data', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->default(0);
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name, email, age) VALUES (?, ?, ?)',
            ['John Doe', 'john@example.com', 30]
        )->await();

        $user = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM users WHERE email = ?', ['john@example.com'])->await();

        expect($user)->not->toBeNull();
        expect($user['name'])->toBe('John Doe');
        expect($user['email'])->toBe('john@example.com');
        expect((int) $user['age'])->toBe(30);
    });

    it('respects default values', function () {
        schema()->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO products (name) VALUES (?)',
            ['Test Product']
        )->await();

        $product = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM products WHERE name = ?', ['Test Product'])->await();

        expect($product)->not->toBeNull();
        expect((float) $product['price'])->toBe(0.00);
        expect((int) $product['stock'])->toBe(0);
        expect((int) $product['active'])->toBe(1);

        schema()->dropIfExists('products')->await();
    });

    it('respects nullable constraints', function () {
        schema()->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bio')->nullable();
            $table->string('website')->nullable();
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO profiles (bio) VALUES (?)',
            [null]
        )->await();

        $profile = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM profiles ORDER BY id DESC LIMIT 1', [])->await();

        expect($profile)->not->toBeNull();
        expect($profile['bio'])->toBeNull();
        expect($profile['website'])->toBeNull();

        schema()->dropIfExists('profiles')->await();
    });

    it('enforces unique constraints', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (email) VALUES (?)',
            ['test@example.com']
        )->await();

        expect(function () {
            Hibla\QueryBuilder\DB::rawExecute(
                'INSERT INTO users (email) VALUES (?)',
                ['test@example.com']
            )->await();
        })->toThrow(Exception::class);
    });

    it('enforces foreign key constraints', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['John Doe']
        )->await();

        $userId = Hibla\QueryBuilder\DB::rawValue('SELECT id FROM users WHERE name = ?', ['John Doe'])->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Test Post']
        )->await();

        $post = Hibla\QueryBuilder\DB::rawFirst('SELECT * FROM posts WHERE title = ?', ['Test Post'])->await();
        expect($post)->not->toBeNull();
        expect((int) $post['user_id'])->toBe((int) $userId);

        expect(function () {
            Hibla\QueryBuilder\DB::rawExecute(
                'INSERT INTO posts (user_id, title) VALUES (?, ?)',
                [99999, 'Invalid Post']
            )->await();
        })->toThrow(Exception::class);
    });

    it('cascades deletes correctly', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['Jane Doe']
        )->await();

        $userId = Hibla\QueryBuilder\DB::rawValue('SELECT id FROM users WHERE name = ?', ['Jane Doe'])->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 1']
        )->await();

        Hibla\QueryBuilder\DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 2']
        )->await();

        $postCount = Hibla\QueryBuilder\DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->await();
        expect((int) $postCount)->toBe(2);

        Hibla\QueryBuilder\DB::rawExecute('DELETE FROM users WHERE id = ?', [$userId])->await();

        $postCount = Hibla\QueryBuilder\DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->await();
        expect((int) $postCount)->toBe(0);
    });
});
