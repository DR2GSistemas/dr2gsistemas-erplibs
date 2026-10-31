<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;
use Dr2g\ErpLibs\CLI\Contracts\AuthRoleRepository;
use Dr2g\ErpLibs\CLI\Contracts\AuthUserRepository;
use Dr2g\ErpLibs\CLI\Scaffold\ProjectContext;

/**
 * `role:assign <email> <rol>[,<rol>...] [--path=<proyecto>]`
 *
 * Asigna roles a un usuario reemplazando los que tuviera. Pensado para
 * automatización y soporte: no requiere pasar por la interfaz web.
 */
class RoleAssignCommand extends Command
{
    public function getName(): string
    {
        return 'role:assign';
    }

    public function getDescription(): string
    {
        return 'Assign roles to a user (replaces the current ones)';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        $positional = [];
        $path = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--path=')) {
                $path = substr($argument, 7);

                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new \InvalidArgumentException("Opción desconocida: {$argument}");
            }

            $positional[] = $argument;
        }

        if (count($positional) < 2) {
            echo "Usage: role:assign <email> <role1,role2> [--path=<proyecto>]\n";

            return;
        }

        [$email, $roles] = $positional;

        $project = ProjectContext::detect($path);
        $this->boot($project);

        $namespace = $project->namespace;
        $userRepository = new ($namespace . '\\Repositories\\UserRepository')();
        $roleRepository = new ($namespace . '\\Repositories\\RoleRepository')();

        if (!$userRepository instanceof AuthUserRepository) {
            throw new \RuntimeException('UserRepository no implementa el contrato requerido por role:assign.');
        }

        if (!$roleRepository instanceof AuthRoleRepository) {
            throw new \RuntimeException('RoleRepository no implementa el contrato requerido por role:assign.');
        }

        $user = $userRepository->findByEmail($email);

        if ($user === null) {
            throw new \RuntimeException("No existe ningún usuario con el email {$email}.");
        }

        $ids = [];

        foreach (array_filter(array_map('trim', explode(',', $roles))) as $name) {
            $role = $roleRepository->findByName($name);

            if ($role === null) {
                throw new \RuntimeException("No existe el rol '{$name}'.");
            }

            $ids[] = (int) $role->toArray()['id'];
        }

        $userRepository->syncRoles($user, $ids);

        echo '[OK] ' . $email . ' -> ' . implode(', ', $ids === [] ? ['(sin roles)'] : array_filter(array_map('trim', explode(',', $roles)))) . "\n";
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
