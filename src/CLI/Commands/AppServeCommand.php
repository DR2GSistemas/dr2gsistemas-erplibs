<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Server\ServerConfig;

/**
 * `app:serve [--port=8000] [--host=127.0.0.1] [--router=public/index.php] [proyecto]`
 *
 * Arranca el servidor embebido de PHP apuntando al `public/index.php` de una
 * aplicación generada con `app:create`.
 */
class AppServeCommand extends Command
{
    public function getName(): string
    {
        return 'app:serve';
    }

    public function getDescription(): string
    {
        return 'Serve a generated app with PHP built-in server (--port=8000)';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
            echo "Usage: app:serve [project] [--port=8000] [--host=127.0.0.1] [--router=public/index.php]\n";
            return;
        }

        $config = ServerConfig::fromArguments(getcwd() ?: '.', $arguments);

        $config->validate();

        if ($config->portIsInUse()) {
            throw new \RuntimeException(
                "El puerto {$config->port} ya está en uso. Prueba con --port=" . ($config->port + 1) . '.'
            );
        }

        $command = implode(' ', array_map('escapeshellarg', $config->command()));

        echo "Serving {$config->project}\n";
        echo "URL: {$config->url()}\n";
        echo "Command: {$command}\n";
        echo "Press Ctrl+C to stop.\n\n";

        passthru($command, $exitCode);

        exit($exitCode);
    }
}
