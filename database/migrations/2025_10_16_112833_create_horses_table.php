<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->create('horses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->dropIfExists('horses');
    }
};
