<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Tests\Helpers\SchemaTestHelper;

beforeEach(function () {
  initializeSchemaForSqlserver();
});

afterEach(function () {
   cleanupSchema();
});


describe('Table Configuration', function () {
    it('creates table with custom engine', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });

    it('creates table with custom charset and collation', function () {
        schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        })->await();

        $exists = schema()->hasTable('users')->await();
        expect($exists)->toBeTruthy();
    });
});