<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->create('humans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('horse_id')->unsigned()->nullable();
            $table->foreign('horse_id')->references('id')->on('horses');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->dropIfExists('humans');
    }
};
