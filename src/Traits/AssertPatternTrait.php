<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait AssertPatternTrait
{
    use ErrorTrait;

    private function assertPattern(
        string $value,
        string $pattern,
        string $name,
    ): void {
        if (!preg_match($pattern, $value)) {
            $this->error("Parameter '$name' contains invalid characters.");
        }
    }
}
