<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Console\Traits;

use Hibla\QueryBuilder\DB;

trait InitializeDatabase
{
    private function initializeDatabase(): void
    {
        try {
            DB::connection($this->connection)->table('_test_init');
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }
    }
}
