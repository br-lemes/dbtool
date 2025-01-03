<?php
declare(strict_types=1);

namespace DBTool;

use DBTool\Commands\CatCommand;
use DBTool\Commands\CopyCommand;
use DBTool\Commands\DiffCommand;
use DBTool\Commands\ListCommand;
use Symfony\Component\Console\Application;

class DBTool extends Application
{
    function __construct()
    {
        parent::__construct('DBTool', $this->version());
        $this->add(new CatCommand());
        $this->add(new CopyCommand());
        $this->add(new DiffCommand());
        $this->add(new ListCommand());
    }

    private function version(): string
    {
        $major = 0;
        $minor = 0;
        $patch = 0;

        $command = 'git log --pretty=format:%s 2>/dev/null';
        $output = [];
        $resultCode = 0;
        chdir(__DIR__);
        exec($command, $output, $resultCode);
        if ($resultCode !== 0) {
            return "$major.$minor.$patch";
        }
        foreach (array_reverse($output) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'fix')) {
                $patch++;
            }
            if (str_starts_with($line, 'feat')) {
                $minor++;
                $patch = 0;
            }
            if (preg_match('/^[a-z]+!:/', $line)) {
                $major++;
                $minor = 0;
                $patch = 0;
            }
        }
        return "$major.$minor.$patch";
    }
}
