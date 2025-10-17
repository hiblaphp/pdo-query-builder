<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->table('horses', function (Blueprint $table) {
            $table->integer('age')->after('id');
        });
    }

    public function down(SchemaBuilder $schema): PromiseInterface
    {
        return $schema->table('horses', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }
};
