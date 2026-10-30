<?php

namespace Dr2g\ErpLibs\CLI;

class Console
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function addCommand(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /** @param list<string> $argv */
    public function run(array $argv): void
    {
        if (count($argv) < 2) {
            $this->showHelp();
            return;
        }

        $commandName = $argv[1];

        if (!isset($this->commands[$commandName])) {
            echo "Command not found: {$commandName}\n";
            $this->showHelp();
            return;
        }

        $command = $this->commands[$commandName];
        $arguments = array_slice($argv, 2);

        try {
            $command->handle($arguments);
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    private function showHelp(): void
    {
        echo "Dr2g ErpLibs CLI\n\n";
        echo "Available commands:\n";

        foreach ($this->commands as $name => $command) {
            echo "  {$name} - {$command->getDescription()}\n";
        }
    }
}
