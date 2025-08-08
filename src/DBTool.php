<?php
declare(strict_types=1);

namespace DBTool;

use DBTool\Commands\CatCommand;
use DBTool\Commands\CopyCommand;
use DBTool\Commands\DiffCommand;
use DBTool\Commands\DumpCommand;
use DBTool\Commands\ListCommand;
use DBTool\Commands\MigrateCommand;
use DBTool\Commands\MigrationCommand;
use DBTool\Commands\MoveCommand;
use DBTool\Commands\RemoveCommand;
use DBTool\Commands\RmAllCommand;
use DBTool\Commands\RollbackCommand;
use DBTool\Commands\RunCommand;
use DBTool\Commands\StatusCommand;
use DBTool\Commands\TruncateCommand;
use Symfony\Component\Console\Application;

class DBTool extends Application
{
    function __construct()
    {
        parent::__construct('DBTool', $this->version());
        $this->add(new CatCommand());
        $this->add(new CopyCommand());
        $this->add(new DiffCommand());
        $this->add(new DumpCommand());
        $this->add(new ListCommand());
        $this->add(new MigrateCommand());
        $this->add(new MigrationCommand());
        $this->add(new MoveCommand());
        $this->add(new RemoveCommand());
        $this->add(new RmAllCommand());
        $this->add(new RollbackCommand());
        $this->add(new RunCommand());
        $this->add(new StatusCommand());
        $this->add(new TruncateCommand());
    }

    private function version(): string
    {
        $major = 0;
        $minor = 0;
        $patch = 0;

        $command = 'git log --pretty=format:%s 2>/dev/null';
        $output = [];
        $resultCode = 0;
        $cwd = getcwd();
        chdir(__DIR__);
        exec($command, $output, $resultCode);
        chdir($cwd);
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
