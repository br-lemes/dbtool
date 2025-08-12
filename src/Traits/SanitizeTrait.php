<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait SanitizeTrait
{
    use ErrorTrait;

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
