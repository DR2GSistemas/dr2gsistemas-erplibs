<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Controladores, rutas y vistas del panel de administración del RBAC.
 *
 * Listados, formularios y borrado de usuarios, roles y permisos, con los
 * permisos `users.*`, `roles.*` y `permissions.view`.
 */
final class AuthAdminScaffold
{
    /**
     * @return array<string, string> ruta relativa => contenido
     */
    public static function files(string $namespace): array
    {
        return [
            'src/Controllers/Admin/UsersController.php' => self::usersController($namespace),
            'src/Controllers/Admin/RolesController.php' => self::rolesController($namespace),
            'src/Controllers/Admin/PermissionsController.php' => self::permissionsController($namespace),
            'src/routes/admin.php' => self::routes($namespace),
            'src/views/admin/dashboard.php' => self::dashboardView(),
            'src/views/admin/users/index.php' => self::usersIndexView(),
            'src/views/admin/users/form.php' => self::usersFormView(),
            'src/views/admin/roles/index.php' => self::rolesIndexView(),
            'src/views/admin/roles/form.php' => self::rolesFormView(),
            'src/views/admin/permissions/index.php' => self::permissionsIndexView(),
            'src/views/errors/419.php' => self::expiredView(),
            'src/views/partials/admin-nav.php' => self::navView(),
            'src/tests/Feature/AdminPanelTest.php' => self::adminTest($namespace),
        ];
    }

    /**
     * Helper compartido por los tres controladores: render con layout.
     */
    private static function renderer(string $namespace): string
    {
        return <<<'PHP'
    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data, int $status = 200): Response
    {
        return (new Renderer([__DIR__ . '/../../views']))
            ->layout($view, 'layout', $data + ['csrf' => $this->csrf->token()], $status);
    }
PHP;
    }

    private static function usersController(string $app): string
    {
        $render = self::renderer($app);

        return <<<PHP
<?php

namespace {$app}\\Controllers\\Admin;

use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use Dr2g\\ErpLibs\\Routing\\Exception\\NotFoundException;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\Validation\\Validator;
use Dr2g\\ErpLibs\\View\\Renderer;
use {$app}\\Models\\User;
use {$app}\\Repositories\\RoleRepository;
use {$app}\\Repositories\\UserRepository;

/**
 * Administración de usuarios y de sus roles.
 */
final class UsersController
{
    public function __construct(
        private readonly UserRepository \$users,
        private readonly RoleRepository \$roles,
        private readonly Hasher \$hasher,
        private readonly CsrfTokenManager \$csrf
    ) {
    }

    public function index(Request \$request): Response
    {
        \$page = max(1, (int) (\$request->query['page'] ?? 1));
        \$result = \$this->users->paginate(page: \$page, perPage: 20, order: ['id', 'DESC']);

        return \$this->render('admin.users.index', [
            'title' => 'Usuarios',
            'users' => \$result['items'],
            'page' => \$result['page'],
            'lastPage' => \$result['last_page'],
            'total' => \$result['total'],
        ]);
    }

    public function create(Request \$request): Response
    {
        return \$this->form(null, [], []);
    }

    public function store(Request \$request): Response
    {
        \$data = \$request->input();
        \$errors = Validator::validate(\$data, [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'min:8'],
        ]);

        if (\$errors === [] && \$this->users->emailExists((string) \$data['email'])) {
            \$errors['email'][] = 'ya está registrado';
        }

        if (\$errors !== []) {
            return \$this->form(null, \$data, \$errors, 422);
        }

        \$user = \$this->users->createWithPassword(\$data, \$this->hasher);
        \$this->users->syncRoles(\$user, \$this->roleIds(\$data));

        return Response::redirect('/admin/users', 303);
    }

    public function edit(Request \$request, int \$id): Response
    {
        \$user = \$this->find(\$id);

        return \$this->form(\$user, \$user->toArray(), [], 200, \$this->users->roleIds(\$user));
    }

    public function update(Request \$request, int \$id): Response
    {
        \$user = \$this->find(\$id);
        \$data = \$request->input();
        \$errors = Validator::validate(\$data, [
            'name' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
        ]);

        if (\$errors !== []) {
            return \$this->form(\$user, \$data, \$errors, 422, \$this->users->roleIds(\$user));
        }

        \$payload = [
            'name' => (string) \$data['name'],
            'email' => (string) \$data['email'],
            'is_active' => isset(\$data['is_active']),
        ];

        if ((string) (\$data['password'] ?? '') !== '') {
            \$payload['password'] = \$this->hasher->hash((string) \$data['password']);
        }

        \$this->users->update(\$user, \$payload);
        \$this->users->syncRoles(\$user, \$this->roleIds(\$data));

        return Response::redirect('/admin/users', 303);
    }

    public function destroy(Request \$request, int \$id): Response
    {
        \$this->users->delete(\$this->find(\$id));

        return Response::redirect('/admin/users', 303);
    }

    private function find(int \$id): User
    {
        /** @var User|null \$user */
        \$user = \$this->users->find(\$id);

        return \$user ?? throw new NotFoundException('Usuario no encontrado.');
    }

    /**
     * @param array<string, mixed> \$data
     * @return list<int>
     */
    private function roleIds(array \$data): array
    {
        \$ids = \$data['roles'] ?? [];

        return is_array(\$ids) ? array_map('intval', \$ids) : [];
    }

    /**
     * @param array<string, mixed> \$values
     * @param array<string, list<string>> \$errors
     * @param list<int> \$selected
     */
    private function form(?User \$user, array \$values, array \$errors, int \$status = 200, array \$selected = []): Response
    {
        return \$this->render('admin.users.form', [
            'title' => \$user === null ? 'Nuevo usuario' : 'Editar usuario',
            'user' => \$user,
            'values' => \$values,
            'errors' => \$errors,
            'roles' => \$this->roles->all(),
            'selected' => \$selected,
        ], \$status);
    }

{$render}
}
PHP;
    }

    private static function rolesController(string $app): string
    {
        $render = self::renderer($app);

        return <<<PHP
<?php

namespace {$app}\\Controllers\\Admin;

use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Routing\\Exception\\NotFoundException;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\Validation\\Validator;
use Dr2g\\ErpLibs\\View\\Renderer;
use {$app}\\Models\\Permission;
use {$app}\\Models\\Role;
use {$app}\\Repositories\\RoleRepository;

/**
 * Administración de roles y de los permisos que agrupan.
 */
final class RolesController
{
    public function __construct(
        private readonly RoleRepository \$roles,
        private readonly CsrfTokenManager \$csrf
    ) {
    }

    public function index(Request \$request): Response
    {
        return \$this->render('admin.roles.index', [
            'title' => 'Roles',
            'roles' => \$this->roles->all(),
        ]);
    }

    public function create(Request \$request): Response
    {
        return \$this->form(null, [], []);
    }

    public function store(Request \$request): Response
    {
        \$data = \$request->input();
        \$errors = Validator::validate(\$data, [
            'name' => ['required', 'max:60'],
            'label' => ['required', 'max:120'],
        ]);

        if (\$errors === [] && \$this->roles->findByName((string) \$data['name']) !== null) {
            \$errors['name'][] = 'ya existe';
        }

        if (\$errors !== []) {
            return \$this->form(null, \$data, \$errors, 422);
        }

        \$role = \$this->roles->create([
            'name' => (string) \$data['name'],
            'label' => (string) \$data['label'],
            'description' => (string) (\$data['description'] ?? ''),
        ]);

        \$this->roles->syncPermissions(\$role, \$this->permissionIds(\$data));

        return Response::redirect('/admin/roles', 303);
    }

    public function edit(Request \$request, int \$id): Response
    {
        \$role = \$this->find(\$id);

        return \$this->form(\$role, \$role->toArray(), [], 200, \$this->roles->permissionIds(\$role));
    }

    public function update(Request \$request, int \$id): Response
    {
        \$role = \$this->find(\$id);
        \$data = \$request->input();
        \$errors = Validator::validate(\$data, [
            'name' => ['required', 'max:60'],
            'label' => ['required', 'max:120'],
        ]);

        if (\$errors !== []) {
            return \$this->form(\$role, \$data, \$errors, 422, \$this->roles->permissionIds(\$role));
        }

        \$this->roles->update(\$role, [
            'name' => (string) \$data['name'],
            'label' => (string) \$data['label'],
            'description' => (string) (\$data['description'] ?? ''),
        ]);

        \$this->roles->syncPermissions(\$role, \$this->permissionIds(\$data));

        return Response::redirect('/admin/roles', 303);
    }

    public function destroy(Request \$request, int \$id): Response
    {
        \$this->roles->delete(\$this->find(\$id));

        return Response::redirect('/admin/roles', 303);
    }

    private function find(int \$id): Role
    {
        /** @var Role|null \$role */
        \$role = \$this->roles->find(\$id);

        return \$role ?? throw new NotFoundException('Rol no encontrado.');
    }

    /**
     * @param array<string, mixed> \$data
     * @return list<int>
     */
    private function permissionIds(array \$data): array
    {
        \$ids = \$data['permissions'] ?? [];

        return is_array(\$ids) ? array_map('intval', \$ids) : [];
    }

    /**
     * @param array<string, mixed> \$values
     * @param array<string, list<string>> \$errors
     * @param list<int> \$selected
     */
    private function form(?Role \$role, array \$values, array \$errors, int \$status = 200, array \$selected = []): Response
    {
        return \$this->render('admin.roles.form', [
            'title' => \$role === null ? 'Nuevo rol' : 'Editar rol',
            'role' => \$role,
            'values' => \$values,
            'errors' => \$errors,
            'permissions' => Permission::all(),
            'selected' => \$selected,
        ], \$status);
    }

{$render}
}
PHP;
    }

    private static function permissionsController(string $app): string
    {
        $render = self::renderer($app);

        return <<<PHP
<?php

namespace {$app}\\Controllers\\Admin;

use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\View\\Renderer;
use {$app}\\Models\\Permission;

/**
 * Catálogo de permisos. Se crean desde el seeder, no desde la interfaz.
 */
final class PermissionsController
{
    public function __construct(private readonly CsrfTokenManager \$csrf)
    {
    }

    public function index(Request \$request): Response
    {
        return \$this->render('admin.permissions.index', [
            'title' => 'Permisos',
            'permissions' => Permission::all(),
        ]);
    }

{$render}
}
PHP;
    }

    private static function routes(string $app): string
    {
        return <<<PHP
<?php

/*
 * Panel de administración. Todas las rutas exigen sesión (`authMiddleware`) y
 * el permiso indicado (`permissionMiddleware('users.view')`).
 *
 * Las dependencias se reciben por variables de ámbito (las define
 * src/routes/panel.php) o, si aún no existen, se construyen aquí: el orden de
 * carga de los archivos de rutas es alfabético y no debe importar.
 *
 * @var \Dr2g\ErpLibs\Routing\Kernel \$kernel
 * @var \Dr2g\ErpLibs\Auth\AuthManager \$auth
 * @var \Dr2g\ErpLibs\Auth\Gate \$gate
 * @var \Dr2g\ErpLibs\Auth\Csrf\CsrfTokenManager \$csrf
 */

if (!isset(\$authMiddleware)) {
    \$authMiddleware = new \Dr2g\ErpLibs\Auth\Middleware\Authenticate(\$auth);
}

if (!isset(\$permissionMiddleware)) {
    \$permissionMiddleware = static fn (string \$permission): callable
        => new \Dr2g\ErpLibs\Auth\Middleware\RequirePermission(\$gate, \$permission, \$kernel->renderer());
}

if (!isset(\$usersController)) {
    \$usersController = new \\{$app}\Controllers\Admin\UsersController(
        new \\{$app}\Repositories\UserRepository(),
        new \\{$app}\Repositories\RoleRepository(),
        new \Dr2g\ErpLibs\Auth\Password\Hasher(),
        \$csrf
    );
}

if (!isset(\$rolesController)) {
    \$rolesController = new \\{$app}\Controllers\Admin\RolesController(
        new \\{$app}\Repositories\RoleRepository(),
        \$csrf
    );
}

if (!isset(\$permissionsController)) {
    \$permissionsController = new \\{$app}\Controllers\Admin\PermissionsController(\$csrf);
}

\$kernel->router()->group('/admin', function (\$router) use (\$usersController, \$permissionMiddleware): void {
    // La entrada del panel lista usuarios (permiso users.view).
    \$router->get('/', \$usersController->index(...));
}, [\$authMiddleware, \$permissionMiddleware('users.view')]);

// Usuarios
\$kernel->router()->group('/admin/users', function (\$router) use (\$usersController, \$permissionMiddleware): void {
    \$router->get('/', \$usersController->index(...));
    \$router->get('/new', \$usersController->create(...));
    \$router->post('/', \$usersController->store(...));
    \$router->get('/{id}/edit', \$usersController->edit(...));
    \$router->post('/{id}', \$usersController->update(...));
    \$router->post('/{id}/delete', \$usersController->destroy(...));
}, [\$authMiddleware, \$permissionMiddleware('users.view')]);

// Roles
\$kernel->router()->group('/admin/roles', function (\$router) use (\$rolesController, \$permissionMiddleware): void {
    \$router->get('/', \$rolesController->index(...));
    \$router->get('/new', \$rolesController->create(...));
    \$router->post('/', \$rolesController->store(...));
    \$router->get('/{id}/edit', \$rolesController->edit(...));
    \$router->post('/{id}', \$rolesController->update(...));
    \$router->post('/{id}/delete', \$rolesController->destroy(...));
}, [\$authMiddleware, \$permissionMiddleware('roles.view')]);

// Permisos
\$kernel->router()->group('/admin/permissions', function (\$router) use (\$permissionsController, \$permissionMiddleware): void {
    \$router->get('/', \$permissionsController->index(...));
}, [\$authMiddleware, \$permissionMiddleware('permissions.view')]);
PHP;
    }

    private static function usersIndexView(): string
    {
        return <<<'PHP'
<?php
/**
 * Listado de usuarios.
 *
 * @var list<\Dr2g\ErpLibs\Models\Model> $users
 * @var int $page
 * @var int $lastPage
 * @var int $total
 * @var string $csrf
 */
?>
<h1>Usuarios <small>(<?= (int) $total ?>)</small></h1>

<?php $nav = $subview ?? null; ?>
<?php if (is_callable($nav)) { echo $nav('partials.admin-nav'); } ?>

<p><a href="/admin/users/new">+ Nuevo usuario</a></p>

<table>
    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Activo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): $row = $user->toArray(); ?>
        <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars((string) $row['name'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars((string) $row['email'], ENT_QUOTES) ?></td>
            <td><?= !empty($row['is_active']) ? 'Sí' : 'No' ?></td>
            <td>
                <a href="/admin/users/<?= (int) $row['id'] ?>/edit">Editar</a>
                <form method="post" action="/admin/users/<?= (int) $row['id'] ?>/delete" style="display:inline">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <button type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($users === []): ?>
        <tr><td colspan="5">No hay usuarios.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($lastPage > 1): ?>
    <p>
        <?php if ($page > 1): ?><a href="/admin/users?page=<?= $page - 1 ?>">Anterior</a><?php endif; ?>
        Página <?= (int) $page ?> de <?= (int) $lastPage ?>
        <?php if ($page < $lastPage): ?><a href="/admin/users?page=<?= $page + 1 ?>">Siguiente</a><?php endif; ?>
    </p>
<?php endif; ?>
PHP;
    }

    private static function usersFormView(): string
    {
        return <<<'PHP'
<?php
/**
 * Formulario de usuario (alta y edición).
 *
 * @var null|\Dr2g\ErpLibs\Models\Model $user
 * @var array<string, mixed> $values
 * @var array<string, list<string>> $errors
 * @var list<\Dr2g\ErpLibs\Models\Model> $roles
 * @var list<int> $selected
 * @var string $csrf
 */
$editing = $user !== null;
$action = $editing ? '/admin/users/' . (int) $user->toArray()['id'] : '/admin/users';
$field = static fn (string $key): string => isset($errors[$key]) ? implode(', ', $errors[$key]) : '';
?>
<h1><?= $editing ? 'Editar' : 'Nuevo' ?> usuario</h1>

<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <p>
        <label for="name">Nombre</label><br>
        <input id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES) ?>" required maxlength="120">
        <?php if ($field('name') !== ''): ?><span role="alert"><?= htmlspecialchars($field('name'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="email">Email</label><br>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES) ?>" required maxlength="180">
        <?php if ($field('email') !== ''): ?><span role="alert"><?= htmlspecialchars($field('email'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="password">Contraseña <?= $editing ? '(dejar vacío para no cambiarla)' : '' ?></label><br>
        <input id="password" name="password" type="password" <?= $editing ? '' : 'required' ?> minlength="8" autocomplete="new-password">
        <?php if ($field('password') !== ''): ?><span role="alert"><?= htmlspecialchars($field('password'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label><input type="checkbox" name="is_active" value="1" <?= !empty($values['is_active']) || !$editing ? 'checked' : '' ?>> Activo</label>
    </p>

    <fieldset>
        <legend>Roles</legend>
        <?php foreach ($roles as $role): $data = $role->toArray(); ?>
            <label>
                <input type="checkbox" name="roles[]" value="<?= (int) $data['id'] ?>" <?= in_array((int) $data['id'], $selected, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars((string) $data['label'], ENT_QUOTES) ?>
            </label><br>
        <?php endforeach; ?>
        <?php if ($roles === []): ?><p>No hay roles definidos.</p><?php endif; ?>
    </fieldset>

    <p>
        <button type="submit"><?= $editing ? 'Guardar' : 'Crear' ?></button>
        <a href="/admin/users">Cancelar</a>
    </p>
</form>
PHP;
    }

    private static function rolesIndexView(): string
    {
        return <<<'PHP'
<?php
/**
 * Listado de roles.
 *
 * @var list<\Dr2g\ErpLibs\Models\Model> $roles
 * @var string $csrf
 */
?>
<h1>Roles</h1>

<?php if (is_callable($subview ?? null)) { echo $subview('partials.admin-nav'); } ?>

<p><a href="/admin/roles/new">+ Nuevo rol</a></p>

<table>
    <thead><tr><th>ID</th><th>Nombre</th><th>Etiqueta</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($roles as $role): $data = $role->toArray(); ?>
        <tr>
            <td><?= (int) $data['id'] ?></td>
            <td><code><?= htmlspecialchars((string) $data['name'], ENT_QUOTES) ?></code></td>
            <td><?= htmlspecialchars((string) $data['label'], ENT_QUOTES) ?></td>
            <td>
                <a href="/admin/roles/<?= (int) $data['id'] ?>/edit">Editar</a>
                <form method="post" action="/admin/roles/<?= (int) $data['id'] ?>/delete" style="display:inline">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <button type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($roles === []): ?><tr><td colspan="4">No hay roles.</td></tr><?php endif; ?>
    </tbody>
</table>
PHP;
    }

    private static function rolesFormView(): string
    {
        return <<<'PHP'
<?php
/**
 * Formulario de rol con selección de permisos.
 *
 * @var null|\Dr2g\ErpLibs\Models\Model $role
 * @var array<string, mixed> $values
 * @var array<string, list<string>> $errors
 * @var list<\Dr2g\ErpLibs\Models\Model> $permissions
 * @var list<int> $selected
 * @var string $csrf
 */
$editing = $role !== null;
$action = $editing ? '/admin/roles/' . (int) $role->toArray()['id'] : '/admin/roles';
$field = static fn (string $key): string => isset($errors[$key]) ? implode(', ', $errors[$key]) : '';
?>
<h1><?= $editing ? 'Editar' : 'Nuevo' ?> rol</h1>

<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <p>
        <label for="name">Nombre interno</label><br>
        <input id="name" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES) ?>" required maxlength="60">
        <?php if ($field('name') !== ''): ?><span role="alert"><?= htmlspecialchars($field('name'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="label">Etiqueta</label><br>
        <input id="label" name="label" value="<?= htmlspecialchars((string) ($values['label'] ?? ''), ENT_QUOTES) ?>" required maxlength="120">
        <?php if ($field('label') !== ''): ?><span role="alert"><?= htmlspecialchars($field('label'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="description">Descripción</label><br>
        <input id="description" name="description" value="<?= htmlspecialchars((string) ($values['description'] ?? ''), ENT_QUOTES) ?>" maxlength="255">
    </p>

    <fieldset>
        <legend>Permisos</legend>
        <?php foreach ($permissions as $permission): $data = $permission->toArray(); ?>
            <label>
                <input type="checkbox" name="permissions[]" value="<?= (int) $data['id'] ?>" <?= in_array((int) $data['id'], $selected, true) ? 'checked' : '' ?>>
                <code><?= htmlspecialchars((string) $data['name'], ENT_QUOTES) ?></code>
            </label><br>
        <?php endforeach; ?>
        <?php if ($permissions === []): ?><p>Ejecuta el seeder para crear los permisos.</p><?php endif; ?>
    </fieldset>

    <p>
        <button type="submit"><?= $editing ? 'Guardar' : 'Crear' ?></button>
        <a href="/admin/roles">Cancelar</a>
    </p>
</form>
PHP;
    }

    private static function permissionsIndexView(): string
    {
        return <<<'PHP'
<?php
/**
 * Catálogo de permisos.
 *
 * @var list<\Dr2g\ErpLibs\Models\Model> $permissions
 */
?>
<h1>Permisos</h1>

<?php if (is_callable($subview ?? null)) { echo $subview('partials.admin-nav'); } ?>

<p>Los permisos se crean con el seeder (<code>php bin/app db:seed</code>).</p>

<table>
    <thead><tr><th>ID</th><th>Nombre</th><th>Etiqueta</th></tr></thead>
    <tbody>
    <?php foreach ($permissions as $permission): $data = $permission->toArray(); ?>
        <tr>
            <td><?= (int) $data['id'] ?></td>
            <td><code><?= htmlspecialchars((string) $data['name'], ENT_QUOTES) ?></code></td>
            <td><?= htmlspecialchars((string) $data['label'], ENT_QUOTES) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($permissions === []): ?><tr><td colspan="3">No hay permisos.</td></tr><?php endif; ?>
    </tbody>
</table>
PHP;
    }

    private static function dashboardView(): string
    {
        return <<<'PHP'
<?php
/**
 * Entrada del panel de administración.
 *
 * @var list<\Dr2g\ErpLibs\Models\Model> $users
 */
?>
<h1>Administración</h1>

<?php if (is_callable($subview ?? null)) { echo $subview('partials.admin-nav'); } ?>

<p>Gestiona usuarios, roles y permisos de la aplicación.</p>
PHP;
    }

    private static function navView(): string
    {
        return <<<'PHP'
<?php
/**
 * Navegación del panel (subview).
 */
?>
<nav>
    <a href="/admin/users">Usuarios</a> ·
    <a href="/admin/roles">Roles</a> ·
    <a href="/admin/permissions">Permisos</a>
</nav>
PHP;
    }

    private static function expiredView(): string
    {
        return <<<'PHP'
<?php
/**
 * Token CSRF caducado o ausente.
 */
?>
<h1>Sesión expirada</h1>
<p>El formulario caducó. Vuelve atrás y recárgalo.</p>
PHP;
    }

    private static function adminTest(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use Dr2g\\ErpLibs\\Auth\\Gate;
use Dr2g\\ErpLibs\\Auth\\Middleware\\RequirePermission;
use Dr2g\\ErpLibs\\Core\\Connection;
use Dr2g\\ErpLibs\\Core\\DatabaseManager;
use Dr2g\\ErpLibs\\Models\\Model;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use {$app}\\Repositories\\UserRepository;
use PHPUnit\\Framework\\TestCase;

/**
 * El panel exige sesión y permiso.
 */
final class AdminPanelTest extends TestCase
{
    private UserRepository \$users;

    protected function setUp(): void
    {
        \$connection = new Connection(['dsn' => 'sqlite::memory:']);
        \$connection->execute('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        \$manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \\ReflectionProperty(DatabaseManager::class, 'connections'))->setValue(\$manager, ['test' => \$connection]);
        Model::setDbManager(\$manager);
        Model::flushMetadata();

        \$this->users = new UserRepository();
    }

    private function middleware(string \$permission): RequirePermission
    {
        return new RequirePermission(new Gate(), \$permission);
    }

    private function request(?array \$permissions): Request
    {
        \$request = Request::create('GET', '/admin/users');

        if (\$permissions === null) {
            return \$request;
        }

        return \$request->withAttributes(['auth_user' => new class(\$permissions) implements \\Dr2g\\ErpLibs\\Auth\\Authorizable {
            private array \$permissions;

            public function __construct(array \$permissions)
            {
                \$this->permissions = \$permissions;
            }

            public function permissionNames(): array
            {
                return \$this->permissions;
            }

            public function getAuthIdentifier(): int|string
            {
                return 1;
            }
        }]);
    }

    public function testWithoutSessionThePanelIsForbidden(): void
    {
        \$response = \$this->middleware('users.view')(
            \$this->request(null),
            static fn (): Response => Response::text('ok')
        );

        self::assertSame(403, \$response->status);
    }

    public function testWithPermissionThePanelIsAllowed(): void
    {
        \$response = \$this->middleware('users.view')(
            \$this->request(['users.view']),
            static fn (): Response => Response::text('ok')
        );

        self::assertSame(200, \$response->status);
    }

    public function testAnotherPermissionIsRejected(): void
    {
        \$response = \$this->middleware('users.delete')(
            \$this->request(['users.view']),
            static fn (): Response => Response::text('ok')
        );

        self::assertSame(403, \$response->status);
    }

    public function testPaginationReturnsMetadata(): void
    {
        \$this->users->createWithPassword(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123'], new \\Dr2g\\ErpLibs\\Auth\\Password\\Hasher());

        \$page = \$this->users->paginate(page: 1, perPage: 20, order: ['id', 'DESC']);

        self::assertSame(1, \$page['total']);
        self::assertCount(1, \$page['items']);
    }
}
PHP;
    }
}
