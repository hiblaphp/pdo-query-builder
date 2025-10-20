<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

class SQLServerForeignKeyCompiler extends ForeignKeyCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '], [';
        $this->openQuote = '[';
        $this->closeQuote = ']';
    }

    public function compile($foreignKey): string
    {
        $cols = implode($this->columnDelimiter, $foreignKey->getColumns());
        $refCols = implode($this->columnDelimiter, $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT [{$foreignKey->getName()}] FOREIGN KEY ([{$cols}]) ".
            "REFERENCES [{$foreignKey->getReferenceTable()}] ([{$refCols}])";

        if ($foreignKey->getOnDelete()) {
            $action = $this->normalizeAction($foreignKey->getOnDelete());
            if ($action !== 'NO ACTION') {
                $sql .= " ON DELETE {$action}";
            }
        }

        if ($foreignKey->getOnUpdate()) {
            $action = $this->normalizeAction($foreignKey->getOnUpdate());
            if ($action !== 'NO ACTION') {
                $sql .= " ON UPDATE {$action}";
            }
        }

        return $sql;
    }

    private function normalizeAction(string $action): string
    {
        return match ($action) {
            'NULL' => 'SET NULL',
            'RESTRICT' => 'NO ACTION',
            default => $action,
        };
    }
}
