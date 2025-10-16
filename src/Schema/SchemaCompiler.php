<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema;

interface SchemaCompiler
{
    public function compileCreate(Blueprint $blueprint): string;
    public function compileAlter(Blueprint $blueprint): string;
    public function compileDrop(string $table): string;
    public function compileDropIfExists(string $table): string;
    public function compileTableExists(string $table): string;
}