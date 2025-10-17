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
            $table->string('name')->index();
            $table->foreignId('horse_id')->constrained('horses');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->dropIfExists('humans');
    }
};
