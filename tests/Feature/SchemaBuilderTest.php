<?php

use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Column;
use Hibla\PdoQueryBuilder\Schema\IndexDefinition;
use Hibla\PdoQueryBuilder\Schema\ForeignKey;
use Hibla\AsyncPDO\AsyncPDO;
use Hibla\PdoQueryBuilder\DB;

function initializeDatabase()
{
    DB::rawExecute("SELECT 1")->await();
}

beforeEach(function () {
    initializeDatabase()->await();
    $this->schema = new SchemaBuilder();
    
    // Clean up test tables
    $tables = ['users', 'posts', 'comments', 'categories', 'tags', 'profiles', 
               'articles', 'locations', 'stats', 'documents', 'financials', 
               'events', 'orders', 'settings', 'temp_table', 'old_name', 'new_name'];
    
    foreach ($tables as $table) {
        try {
            $this->schema->dropIfExists($table)->await();
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }
    }
});

afterEach(function () {
    // Clean up test tables after each test
    $tables = ['users', 'posts', 'comments', 'categories', 'tags', 'profiles', 
               'articles', 'locations', 'stats', 'documents', 'financials', 
               'events', 'orders', 'settings', 'temp_table', 'old_name', 'new_name'];
    
    foreach ($tables as $table) {
        try {
            $this->schema->dropIfExists($table)->await();
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }
    }
});

describe('Table Creation', function () {
    it('creates a basic table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with various column types', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with auto-increment columns', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->increments('legacy_id');
            $table->bigIncrements('big_id');
            $table->smallIncrements('small_id');
            $table->string('name');
        })->await();

        $exists = $this->schema->hasTable('categories')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with integer variations', function () {
        $this->schema->create('stats', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('tiny_num');
            $table->smallInteger('small_num');
            $table->mediumInteger('medium_num');
            $table->integer('regular_num');
            $table->bigInteger('big_num');
            $table->unsignedTinyInteger('unsigned_tiny');
            $table->unsignedBigInteger('unsigned_big');
        })->await();

        $exists = $this->schema->hasTable('stats')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with text variations', function () {
        $this->schema->create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('short_text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->string('title', 100);
        })->await();

        $exists = $this->schema->hasTable('documents')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with decimal variations', function () {
        $this->schema->create('financials', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->float('rate', 5, 2);
            $table->double('precise_value', 15, 8);
            $table->unsignedDecimal('positive_amount', 8, 2);
        })->await();

        $exists = $this->schema->hasTable('financials')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with date/time columns', function () {
        $this->schema->create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->dateTime('event_datetime');
            $table->timestamp('event_timestamp');
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('events')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with enum column', function () {
        $this->schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('orders')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with soft deletes', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with comments', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User full name');
            $table->string('email')->comment('User email address')->unique();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with after positioning', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->after('name');
            $table->timestamps();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Indexes', function () {
    it('creates a table with primary key', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with unique index', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with regular index', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with composite index', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with fulltext index', function () {
        $this->schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        })->await();

        $exists = $this->schema->hasTable('articles')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with spatial index', function () {
        $this->schema->create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('coordinates')->spatialIndex();
        })->await();

        $exists = $this->schema->hasTable('locations')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with named indexes', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates a table with index algorithms', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Foreign Keys', function () {
    it('creates tables with foreign key constraint', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with cascade on delete', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with cascade on update', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with custom reference', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with various actions', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        })->await();

        $exists = $this->schema->hasTable('profiles')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with null on delete', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with restrict actions', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates foreign key with no action', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->noActionOnDelete()->noActionOnUpdate();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
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

describe('Column Attributes', function () {
    it('creates columns with nullable attribute', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable(false);
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with default values', function () {
        $this->schema->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value')->default('default_value');
            $table->integer('count')->default(0);
            $table->boolean('active')->default(true);
            $table->decimal('amount', 10, 2)->default(0.00);
        })->await();

        $exists = $this->schema->hasTable('settings')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with unsigned attribute', function () {
        $this->schema->create('counters', function (Blueprint $table) {
            $table->id();
            $table->integer('count')->unsigned();
            $table->bigInteger('big_count')->unsigned();
        })->await();

        $exists = $this->schema->hasTable('counters')->await();
        expect($exists)->toBeTrue();
    });

    it('creates columns with useCurrent for timestamps', function () {
        $this->schema->create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->onUpdate('CURRENT_TIMESTAMP');
        })->await();

        $exists = $this->schema->hasTable('logs')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Table Configuration', function () {
    it('creates table with custom engine', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('creates table with custom charset and collation', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Complex Scenarios', function () {
    it('creates a complete blog schema', function () {
        // Create users table
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        // Create categories table
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        })->await();

        // Create posts table with relationships
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

        // Create comments table
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
        // Create initial table
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('old_email');
            $table->integer('age');
        })->await();

        // Perform multiple alterations
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

describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        $this->schema->create('empty_table', function (Blueprint $table) {
            $table->id();
        })->await();

        $exists = $this->schema->hasTable('empty_table')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('empty_table')->await();
    });

    it('handles table with many columns', function () {
        $this->schema->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        })->await();

        $exists = $this->schema->hasTable('wide_table')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('wide_table')->await();
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = $this->schema->dropIfExists('this_table_does_not_exist')->await();
        expect($result)->not->toThrow(Exception::class);
    });
});

describe('Data Insertion and Verification', function () {
    it('creates table and inserts data', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->default(0);
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (name, email, age) VALUES (?, ?, ?)',
            ['John Doe', 'john@example.com', 30]
        )->await();

        $user = AsyncPDO::fetchOne('SELECT * FROM users WHERE email = ?', ['john@example.com'])->await();
        
        expect($user)->not->toBeNull();
        expect($user['name'])->toBe('John Doe');
        expect($user['email'])->toBe('john@example.com');
        expect((int)$user['age'])->toBe(30);
    });

    it('respects default values', function () {
        $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
        })->await();

        AsyncPDO::execute(
            'INSERT INTO products (name) VALUES (?)',
            ['Test Product']
        )->await();

        $product = AsyncPDO::fetchOne('SELECT * FROM products WHERE name = ?', ['Test Product'])->await();
        
        expect($product)->not->toBeNull();
        expect((float)$product['price'])->toBe(0.00);
        expect((int)$product['stock'])->toBe(0);
        expect((int)$product['active'])->toBe(1);
        
        $this->schema->dropIfExists('products')->await();
    });

    it('respects nullable constraints', function () {
        $this->schema->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bio')->nullable();
            $table->string('website')->nullable();
        })->await();

        AsyncPDO::execute(
            'INSERT INTO profiles (bio) VALUES (?)',
            [null]
        )->await();

        $profile = AsyncPDO::fetchOne('SELECT * FROM profiles ORDER BY id DESC LIMIT 1', [])->await();
        
        expect($profile)->not->toBeNull();
        expect($profile['bio'])->toBeNull();
        expect($profile['website'])->toBeNull();
        
        $this->schema->dropIfExists('profiles')->await();
    });

    it('enforces unique constraints', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (email) VALUES (?)',
            ['test@example.com']
        )->await();

        expect(function () {
            AsyncPDO::execute(
                'INSERT INTO users (email) VALUES (?)',
                ['test@example.com']
            )->await();
        })->toThrow(Exception::class);
    });

    it('enforces foreign key constraints', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (name) VALUES (?)',
            ['John Doe']
        )->await();

        $userId = AsyncPDO::fetchValue('SELECT id FROM users WHERE name = ?', ['John Doe'])->await();

        AsyncPDO::execute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Test Post']
        )->await();

        $post = AsyncPDO::fetchOne('SELECT * FROM posts WHERE title = ?', ['Test Post'])->await();
        expect($post)->not->toBeNull();
        expect((int)$post['user_id'])->toBe((int)$userId);

        // Try to insert with non-existent user_id
        expect(function () {
            AsyncPDO::execute(
                'INSERT INTO posts (user_id, title) VALUES (?, ?)',
                [99999, 'Invalid Post']
            )->await();
        })->toThrow(Exception::class);
    });

    it('cascades deletes correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (name) VALUES (?)',
            ['Jane Doe']
        )->await();

        $userId = AsyncPDO::fetchValue('SELECT id FROM users WHERE name = ?', ['Jane Doe'])->await();

        AsyncPDO::execute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 1']
        )->await();

        AsyncPDO::execute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 2']
        )->await();

        $postCount = AsyncPDO::fetchValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->await();
        expect((int)$postCount)->toBe(2);

        // Delete user
        AsyncPDO::execute('DELETE FROM users WHERE id = ?', [$userId])->await();

        // Check posts are also deleted
        $postCount = AsyncPDO::fetchValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId])->await();
        expect((int)$postCount)->toBe(0);
    });
});

describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates timestamps helper correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        })->await();

        AsyncPDO::execute(
            'INSERT INTO users (name) VALUES (?)',
            ['Test User']
        )->await();

        $user = AsyncPDO::fetchOne('SELECT * FROM users WHERE name = ?', ['Test User'])->await();
        
        expect($user['created_at'])->not->toBeNull();
        expect($user['updated_at'])->not->toBeNull();
    });

    it('creates softDeletes helper correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Blueprint Methods', function () {
    it('gets blueprint properties correctly', function () {
        $blueprint = new Blueprint('test_table');
        
        expect($blueprint->getTable())->toBe('test_table');
        expect($blueprint->getEngine())->toBe('InnoDB');
        expect($blueprint->getCharset())->toBe('utf8mb4');
        expect($blueprint->getCollation())->toBe('utf8mb4_unicode_ci');
    });

    it('sets blueprint engine correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('sets blueprint charset and collation correctly', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Column Class', function () {
    it('creates column with correct attributes', function () {
        $column = new Column('name', 'VARCHAR', 255);
        
        expect($column->getName())->toBe('name');
        expect($column->getType())->toBe('VARCHAR');
        expect($column->getLength())->toBe(255);
    });

    it('sets column nullable', function () {
        $column = new Column('email', 'VARCHAR', 255);
        $column->nullable();
        
        expect($column->isNullable())->toBeTrue();
    });

    it('sets column default value', function () {
        $column = new Column('age', 'INT');
        $column->default(0);
        
        expect($column->hasDefault())->toBeTrue();
        expect($column->getDefault())->toBe(0);
    });

    it('sets column unsigned', function () {
        $column = new Column('count', 'INT');
        $column->unsigned();
        
        expect($column->isUnsigned())->toBeTrue();
    });

    it('sets column auto increment', function () {
        $column = new Column('id', 'BIGINT');
        $column->autoIncrement();
        
        expect($column->isAutoIncrement())->toBeTrue();
    });

    it('sets column primary', function () {
        $column = new Column('id', 'BIGINT');
        $column->primary();
        
        expect($column->isPrimary())->toBeTrue();
    });

    it('sets column unique', function () {
        $column = new Column('email', 'VARCHAR', 255);
        $column->unique();
        
        expect($column->isUnique())->toBeTrue();
    });

    it('sets column comment', function () {
        $column = new Column('name', 'VARCHAR', 255);
        $column->comment('User full name');
        
        expect($column->getComment())->toBe('User full name');
    });

    it('converts column to array', function () {
        $column = new Column('name', 'VARCHAR', 255);
        $column->nullable()->default('John')->comment('User name');
        
        $array = $column->toArray();
        
        expect($array['name'])->toBe('name');
        expect($array['type'])->toBe('VARCHAR');
        expect($array['length'])->toBe(255);
        expect($array['nullable'])->toBeTrue();
        expect($array['default'])->toBe('John');
        expect($array['comment'])->toBe('User name');
    });

    it('creates column from array', function () {
        $data = [
            'name' => 'email',
            'type' => 'VARCHAR',
            'length' => 255,
            'nullable' => true,
            'hasDefault' => true,
            'default' => 'test@example.com',
            'unique' => true,
            'comment' => 'User email',
        ];
        
        $column = Column::fromArray($data);
        
        expect($column->getName())->toBe('email');
        expect($column->getType())->toBe('VARCHAR');
        expect($column->getLength())->toBe(255);
        expect($column->isNullable())->toBeTrue();
        expect($column->getDefault())->toBe('test@example.com');
        expect($column->isUnique())->toBeTrue();
        expect($column->getComment())->toBe('User email');
    });

    it('copies column with new name', function () {
        $column = new Column('old_name', 'VARCHAR', 255);
        $column->nullable()->default('test');
        
        $newColumn = $column->copyWithName('new_name');
        
        expect($newColumn->getName())->toBe('new_name');
        expect($newColumn->getType())->toBe('VARCHAR');
        expect($newColumn->getLength())->toBe(255);
        expect($newColumn->isNullable())->toBeTrue();
        expect($newColumn->getDefault())->toBe('test');
    });
});

describe('IndexDefinition Class', function () {
    it('creates index definition with correct attributes', function () {
        $index = new IndexDefinition('INDEX', ['email'], 'users_email_index');
        
        expect($index->getType())->toBe('INDEX');
        expect($index->getColumns())->toBe(['email']);
        expect($index->getName())->toBe('users_email_index');
    });

    it('sets index algorithm', function () {
        $index = new IndexDefinition('INDEX', ['email'], 'users_email_index');
        $index->algorithm('BTREE');
        
        expect($index->getAlgorithm())->toBe('BTREE');
    });

    it('sets index operator class', function () {
        $index = new IndexDefinition('SPATIAL', ['location'], 'locations_location_spatial');
        $index->operatorClass('gist');
        
        expect($index->getOperatorClass())->toBe('gist');
    });

    it('converts index to array', function () {
        $index = new IndexDefinition('UNIQUE', ['email'], 'users_email_unique');
        $index->algorithm('BTREE');
        
        $array = $index->toArray();
        
        expect($array['type'])->toBe('UNIQUE');
        expect($array['name'])->toBe('users_email_unique');
        expect($array['columns'])->toBe(['email']);
        expect($array['algorithm'])->toBe('BTREE');
    });
});

describe('ForeignKey Class', function () {
    it('creates foreign key with correct attributes', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        
        expect($foreignKey->getName())->toBe('posts_user_id_foreign');
        expect($foreignKey->getColumns())->toBe(['user_id']);
        expect($foreignKey->getBlueprintTable())->toBe('posts');
    });

    it('sets foreign key references', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->references('id')->on('users');
        
        expect($foreignKey->getReferenceTable())->toBe('users');
        expect($foreignKey->getReferenceColumns())->toBe(['id']);
    });

    it('sets foreign key on delete action', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->onDelete('CASCADE');
        
        expect($foreignKey->getOnDelete())->toBe('CASCADE');
    });

    it('sets foreign key on update action', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->onUpdate('CASCADE');
        
        expect($foreignKey->getOnUpdate())->toBe('CASCADE');
    });

    it('uses cascade on delete helper', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->cascadeOnDelete();
        
        expect($foreignKey->getOnDelete())->toBe('CASCADE');
    });

    it('uses cascade on update helper', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->cascadeOnUpdate();
        
        expect($foreignKey->getOnUpdate())->toBe('CASCADE');
    });

    it('uses null on delete helper', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->nullOnDelete();
        
        expect($foreignKey->getOnDelete())->toBe('SET NULL');
    });

    it('uses restrict helpers', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->restrictOnDelete()->restrictOnUpdate();
        
        expect($foreignKey->getOnDelete())->toBe('RESTRICT');
        expect($foreignKey->getOnUpdate())->toBe('RESTRICT');
    });

    it('uses no action helpers', function () {
        $foreignKey = new ForeignKey('posts_user_id_foreign', ['user_id'], 'posts');
        $foreignKey->noActionOnDelete()->noActionOnUpdate();
        
        expect($foreignKey->getOnDelete())->toBe('NO ACTION');
        expect($foreignKey->getOnUpdate())->toBe('NO ACTION');
    });
});

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        })->await();

        $this->schema->dropColumn('users', 'email')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses renameColumn helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->renameColumn('users', 'name', 'full_name')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses dropIndex helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        })->await();

        $this->schema->dropIndex('users', 'users_email_index')->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });

    it('uses dropForeign helper method', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        })->await();

        $this->schema->dropForeign('posts', 'posts_user_id_foreign')->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Complex Foreign Key Relationships', function () {
    it('creates multiple foreign keys on single table', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates self-referencing foreign key', function () {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        })->await();

        $exists = $this->schema->hasTable('categories')->await();
        expect($exists)->toBeTrue();
    });

    it('creates composite foreign key', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        })->await();

        $this->schema->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bio');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        })->await();

        $exists = $this->schema->hasTable('user_profiles')->await();
        expect($exists)->toBeTrue();
    });
});

describe('Advanced Index Scenarios', function () {
    it('creates multiple indexes on same column', function () {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('content');
            $table->index('title', 'custom_title_index');
        })->await();

        $exists = $this->schema->hasTable('posts')->await();
        expect($exists)->toBeTrue();
    });

    it('creates composite unique index', function () {
        $this->schema->create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        })->await();

        $exists = $this->schema->hasTable('user_roles')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('user_roles')->await();
    });

    it('creates indexes with custom names', function () {
        $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index('idx_product_sku');
            $table->string('name')->unique('unq_product_name');
        })->await();

        $exists = $this->schema->hasTable('products')->await();
        expect($exists)->toBeTrue();
        
        $this->schema->dropIfExists('products')->await();
    });
});

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";
            
            $this->schema->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            })->await();

            $exists = $this->schema->hasTable($tableName)->await();
            expect($exists)->toBeTrue();

            $this->schema->drop($tableName)->await();

            $exists = $this->schema->hasTable($tableName)->await();
            expect($exists)->toBeFalse();
        }
    });

    it('handles multiple alterations in sequence', function () {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        })->await();

        // Add columns
        $this->schema->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        })->await();

        // Add index
        $this->schema->table('users', function (Blueprint $table) {
            $table->index('email');
        })->await();

        // Rename column
        $this->schema->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        })->await();

        // Add another column
        $this->schema->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        })->await();

        $exists = $this->schema->hasTable('users')->await();
        expect($exists)->toBeTrue();
    });
});