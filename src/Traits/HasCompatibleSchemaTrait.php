<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait HasCompatibleSchemaTrait
{
    private function hasCompatibleSchema(string $table): bool
    {
        $columns1 = $this->db1->getColumns($table, 'native');
        $columns2 = $this->db2->getColumns($table, 'native');
        if ($columns1 === null || $columns2 === null) {
            return false; // @codeCoverageIgnore
        }
        $names1 = array_column($columns1, 'COLUMN_NAME');
        $names2 = array_column($columns2, 'COLUMN_NAME');
        sort($names1);
        sort($names2);
        return $names1 === $names2;
    }
}
