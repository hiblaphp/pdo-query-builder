<?php

use Hibla\PdoQueryBuilder\Schema\Column;

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
        
        expect($column->isNullable())->toBeTruthy();
    });

    it('sets column default value', function () {
        $column = new Column('age', 'INT');
        $column->default(0);
        
        expect($column->hasDefault())->toBeTruthy();
        expect($column->getDefault())->toBe(0);
    });

    it('sets column unsigned', function () {
        $column = new Column('count', 'INT');
        $column->unsigned();
        
        expect($column->isUnsigned())->toBeTruthy();
    });

    it('sets column auto increment', function () {
        $column = new Column('id', 'BIGINT');
        $column->autoIncrement();
        
        expect($column->isAutoIncrement())->toBeTruthy();
    });

    it('sets column primary', function () {
        $column = new Column('id', 'BIGINT');
        $column->primary();
        
        expect($column->isPrimary())->toBeTruthy();
    });

    it('sets column unique', function () {
        $column = new Column('email', 'VARCHAR', 255);
        $column->unique();
        
        expect($column->isUnique())->toBeTruthy();
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
        expect($array['nullable'])->toBeTruthy();
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
        expect($column->isNullable())->toBeTruthy();
        expect($column->getDefault())->toBe('test@example.com');
        expect($column->isUnique())->toBeTruthy();
        expect($column->getComment())->toBe('User email');
    });

    it('copies column with new name', function () {
        $column = new Column('old_name', 'VARCHAR', 255);
        $column->nullable()->default('test');
        
        $newColumn = $column->copyWithName('new_name');
        
        expect($newColumn->getName())->toBe('new_name');
        expect($newColumn->getType())->toBe('VARCHAR');
        expect($newColumn->getLength())->toBe(255);
        expect($newColumn->isNullable())->toBeTruthy();
        expect($newColumn->getDefault())->toBe('test');
    });
});