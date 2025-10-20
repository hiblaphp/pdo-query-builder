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
     */
    public function compileAddIndexDefinition(string $table, IndexDefinition $indexDef): array
    {
        $type = $indexDef->getType();
        $cols = $this->getColumnsList($indexDef);
        $statements = [];

        if ($type === 'PRIMARY') {
            $sql = "ALTER TABLE `{$table}` ADD PRIMARY KEY (`{$cols}`)";
            if ($indexDef->getAlgorithm()) {
                $sql .= " USING {$indexDef->getAlgorithm()}";
            }
            $statements[] = $sql;
        } elseif ($type === 'UNIQUE') {
            $sql = "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexDef->getName()}` (`{$cols}`)";
            if ($indexDef->getAlgorithm()) {
                $sql .= " USING {$indexDef->getAlgorithm()}";
            }
            $statements[] = $sql;
        } elseif ($type === 'FULLTEXT') {
            $sql = "ALTER TABLE `{$table}` ADD FULLTEXT KEY `{$indexDef->getName()}` (`{$cols}`)";
            if ($indexDef->getAlgorithm()) {
                $sql .= " WITH PARSER {$indexDef->getAlgorithm()}";
            }
            $statements[] = $sql;
        } elseif ($type === 'SPATIAL') {
            $statements[] = "ALTER TABLE `{$table}` ADD SPATIAL KEY `{$indexDef->getName()}` (`{$cols}`)";
        } elseif ($type !== 'RAW') {
            $sql = "ALTER TABLE `{$table}` ADD KEY `{$indexDef->getName()}` (`{$cols}`)";
            if ($indexDef->getAlgorithm()) {
                $sql .= " USING {$indexDef->getAlgorithm()}";
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    /**
     * Override parent's compileFulltextIndex to add algorithm support
     */
    protected function compileFulltextIndex(IndexDefinition $indexDef): string
    {
        $cols = $this->getColumnsList($indexDef);
        $name = $indexDef->getName();
        $sql = "FULLTEXT KEY `{$name}` (`{$cols}`)";

        if ($indexDef->getAlgorithm()) {
            $sql .= " WITH PARSER {$indexDef->getAlgorithm()}";
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

        if ($indexDef->getAlgorithm()) {
            $sql .= " USING {$indexDef->getAlgorithm()}";
        }

        return $sql;
    }
}