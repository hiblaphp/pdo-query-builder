<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Migration;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class extends Migration
{
    /**
     * Run the migration.
     *
     * @return PromiseInterface<int|null>
     */
    public function up(): PromiseInterface
    {
        return $this->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migration.
     *
     * @return PromiseInterface<int>
     */
    public function down(): PromiseInterface
    {
        return $this->dropIfExists('users');
    }
};
