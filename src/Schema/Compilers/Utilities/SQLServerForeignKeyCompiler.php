<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Schema\Compilers\Utilities;

use Hibla\PdoQueryBuilder\Schema\ForeignKey;

class SQLServerForeignKeyCompiler extends ForeignKeyCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '], [';
        $this->openQuote = '[';
        $this->closeQuote = ']';
    }

    public function compile(ForeignKey $foreignKey): string
    {
        $cols = implode($this->columnDelimiter, $foreignKey->getColumns());
        $refCols = implode($this->columnDelimiter, $foreignKey->getReferenceColumns());

        $sql = "CONSTRAINT [{$foreignKey->getName()}] FOREIGN KEY ([{$cols}]) ".
            "REFERENCES [{$foreignKey->getReferenceTable()}] ([{$refCols}])";

        $onDelete = $foreignKey->getOnDelete();
        if ($onDelete !== '') {
            $action = $this->normalizeAction($onDelete);
            if ($action !== 'NO ACTION') {
                $sql .= " ON DELETE {$action}";
            }
        }

        $onUpdate = $foreignKey->getOnUpdate();
        if ($onUpdate !== '') {
            $action = $this->normalizeAction($onUpdate);
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
