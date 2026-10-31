<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `permission:list [--path=<proyecto>]`
 *
 * Lista los permisos y los roles que los conceden. Útil en soporte para saber
 * por qué un usuario no puede acceder a una sección.
 */
class PermissionListCommand extends Command
{
    public function getName(): string
    {
        return 'permission:list';
    }

    public function getDescription(): string
    {
        return 'List permissions with the roles that grant them';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $path = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--path=')) {
                $path = substr($argument, 7);
            }
        }

        $project = ProjectContext::detect($path);
        $this->boot($project);

        $namespace = $project->namespace;
        $permissionClass = $namespace . '\\Models\\Permission';
        $connection = $permissionClass::connection();

        $rows = $connection->select(
            'SELECT p.name AS permission,'
            . ' GROUP_CONCAT(r.name) AS roles'
            . ' FROM permissions p'
            . ' LEFT JOIN permission_role pr ON pr.permission_id = p.id'
            . ' LEFT JOIN roles r ON r.id = pr.role_id'
            . ' GROUP BY p.id, p.name'
            . ' ORDER BY p.name'
        );

        if ($rows === []) {
            echo "No hay permisos. Ejecuta: php bin/app db:seed\n";

            return;
        }

        printf("%-24s %s\n", 'PERMISO', 'ROLES');
        echo str_repeat('-', 60) . "\n";

        foreach ($rows as $row) {
            printf("%-24s %s\n", (string) $row['permission'], (string) ($row['roles'] ?? '-'));
        }
    }

    private function boot(ProjectContext $project): void
    {
        require_once $project->path('vendor/autoload.php');

        if (is_file($project->path('.env'))) {
            \Dr2g\ErpLibs\Config\DotEnv::load($project->path('.env'));
        }

        $config = require $project->path('src/config/database.php');
        \Dr2g\ErpLibs\Models\Model::setDbManager(new \Dr2g\ErpLibs\Core\DatabaseManager($config));
    }
}
