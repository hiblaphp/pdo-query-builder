<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\IndexDefinition;

class MySQLIndexCompiler extends IndexCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '`, `';
        $this->openQuote = '`';
        $this->closeQuote = '`';
    }

    /**
     * Add index to existing table via ALTER TABLE
     *
     * @return list<string>
     */
    public function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();
        $algorithm = $indexDef->getAlgorithm();

        $sql = match ($type) {
            'PRIMARY' => $this->buildPrimaryAlter($table, $cols, $algorithm),
            'UNIQUE' => $this->buildUniqueAlter($table, $name, $cols, $algorithm),
            'FULLTEXT' => $this->buildFulltextAlter($table, $name, $cols, $algorithm),
            'SPATIAL' => "ALTER TABLE `{$table}` ADD SPATIAL KEY `{$name}` (`{$cols}`)",
            'RAW' => null,
            default => $this->buildRegularAlter($table, $name, $cols, $algorithm),
        };

        return $sql !== null ? [$sql] : [];
    }

    private function buildPrimaryAlter(string $table, string $cols, ?string $algorithm): string
    {
        $sql = "ALTER TABLE `{$table}` ADD PRIMARY KEY (`{$cols}`)";
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " USING {$algorithm}";
        }

        return $sql;
    }

    private function buildUniqueAlter(string $table, ?string $name, string $cols, ?string $algorithm): string
    {
        $sql = "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$name}` (`{$cols}`)";
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " USING {$algorithm}";
        }

        return $sql;
    }

    private function buildFulltextAlter(string $table, ?string $name, string $cols, ?string $algorithm): string
    {
        $sql = "ALTER TABLE `{$table}` ADD FULLTEXT KEY `{$name}` (`{$cols}`)";
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " WITH PARSER {$algorithm}";
        }

        return $sql;
    }

    private function buildRegularAlter(string $table, ?string $name, string $cols, ?string $algorithm): string
    {
        $sql = "ALTER TABLE `{$table}` ADD KEY `{$name}` (`{$cols}`)";
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " USING {$algorithm}";
        }

        return $sql;
    }

    /**
     * Override parent's compileFulltextIndex to add algorithm support
     */
    protected function compileFulltextIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();
        $sql = "FULLTEXT KEY `{$name}` (`{$cols}`)";

        $algorithm = $indexDef->getAlgorithm();
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " WITH PARSER {$algorithm}";
        }

        return $sql;
    }

    /**
     * Override parent's compileRegularIndex to add algorithm support
     */
    protected function compileRegularIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();
        $sql = "KEY `{$name}` (`{$cols}`)";

        $algorithm = $indexDef->getAlgorithm();
        if ($algorithm !== null && $algorithm !== '') {
            $sql .= " USING {$algorithm}";
        }

        return $sql;
    }
}
