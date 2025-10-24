<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Migration;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class () extends Migration {
    protected ?string $connection = 'postgres';

    /**
     * Run the migration.
     *
     * @return PromiseInterface<int|null>
     */
    public function up(): PromiseInterface
    {
        return $this->create('shoes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand');
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
        return $this->dropIfExists('shoes');
    }
};
