<?php

use Hibla\PdoQueryBuilder\Schema\IndexDefinition;

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