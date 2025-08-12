<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConfigTrait;
use DBTool\Traits\ConstTrait;
use Exception;
use PHPUnit\Framework\TestCase;

class ConfigTraitTest extends TestCase
{
    use ConfigTrait;
    use ConstTrait;

    const CFG_FILE = __DIR__ . '/../../config/test-config.php';
    const CFG_NAME = 'test-config';

    function tearDown(): void
    {
        if (is_file(self::CFG_FILE)) {
            unlink(self::CFG_FILE);
        }
        parent::tearDown();
    }

    function testMissingFile(): void
    {
        $message = sprintf(self::FAILED_CONFIG, realpath(self::CFG_FILE));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        $this->getConfig(self::CFG_NAME);
    }

    function testMissingRequired(): void
    {
        $cfg = "<?php return ['driver' => 'mysql'];";
        $message = sprintf(self::MISSING_REQUIRED, 'host');
        file_put_contents(self::CFG_FILE, $cfg);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        $this->getConfig(self::CFG_NAME, ['host']);
    }

    function testMissingNested(): void
    {
        $cfg = "<?php return ['driver' => 'mysql'];";
        $message = sprintf(self::MISSING_REQUIRED, 'paths.migrations');
        file_put_contents(self::CFG_FILE, $cfg);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        $this->getConfig(self::CFG_NAME, ['paths.migrations']);
    }

    function testUnsupportedDriver(): void
    {
        $cfg = "<?php return ['driver' => 'invalid'];";
        $message = sprintf(self::UNSUPPORTED_DRIVER, 'invalid');
        file_put_contents(self::CFG_FILE, $cfg);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        $this->getConfig(self::CFG_NAME);
    }
}
