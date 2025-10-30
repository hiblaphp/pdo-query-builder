<?php

declare(strict_types=1);

use Hibla\QueryBuilder\Schema\ForeignKey;

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
