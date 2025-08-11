<?php
declare(strict_types=1);

namespace DBTool\Traits;

use Exception;
use PDOException;

trait UtilitiesTrait
{
    function error(string $message, ?PDOException $e = null): void
    {
        throw new Exception($e ? "$message: {$e->getMessage()}" : $message);
    }

    private function sanitize(
        string $value,
        string $pattern,
        string $name,
    ): string {
        if (!preg_match($pattern, $value)) {
            $this->error("Parameter '$name' contains invalid characters.");
        }
        return $value;
    }
}
