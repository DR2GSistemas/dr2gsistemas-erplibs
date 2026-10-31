<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Plantillas de `make:auth`: autenticación con RBAC (usuarios, roles, permisos).
 *
 * La infraestructura (sesión, CSRF, Gate, middleware) vive en la librería
 * (`Dr2g\ErpLibs\Auth`); aquí se generan los artefactos concretos del proyecto.
 */
final class AuthScaffold
{
    public const MIGRATION = 'migrations/2026_01_01_100000_create_auth_tables.php';

    /**
     * Permisos iniciales agrupados por recurso.
     *
     * @return array<string, list<string>>
     */
    public static function permissions(): array
    {
        return [
            'users' => ['view', 'create', 'update', 'delete'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'permissions' => ['view'],
        ];
    }

    /**
     * Lista plana de nombres de permiso: `users.view`, `roles.create`…
     *
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (self::permissions() as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /**
     * @return array<string, string> ruta relativa => contenido
     */
    public static function files(string $namespace): array
    {
        return [
            'src/Models/User.php' => self::user($namespace),
            'src/Models/Role.php' => self::role($namespace),
            'src/Models/Permission.php' => self::permission($namespace),
            'src/Repositories/UserRepository.php' => self::userRepository($namespace),
            'src/Repositories/RoleRepository.php' => self::roleRepository($namespace),
            self::MIGRATION => self::migration(),
            'database/seeders/AuthSeeder.php' => self::seeder($namespace),
            'src/Http/Middleware/StartSession.php' => self::startSession($namespace),
            'src/Http/Middleware/VerifyCsrf.php' => self::verifyCsrf($namespace),
            'src/Controllers/AuthController.php' => self::authController($namespace),
            'src/Controllers/RegisterController.php' => self::registerController($namespace),
            'src/routes/auth.php' => self::routes($namespace),
            'src/views/auth/login.php' => self::loginView($namespace),
            'src/views/auth/register.php' => self::registerView($namespace),
            'src/views/errors/403.php' => self::forbiddenView($namespace),
            'src/views/auth/password.php' => self::passwordView(),
            'src/tests/Feature/AuthTest.php' => self::authTest($namespace),
        ] + AuthAdminScaffold::files($namespace);
    }

    private static function startSession(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Http\\Middleware;

use Dr2g\\ErpLibs\\Auth\\Session\\SessionInterface;
use Dr2g\\ErpLibs\\Routing\\Request;

/**
 * Arranca la sesión antes de que cualquier ruta consulte al usuario.
 *
 * Idempotente: `Session::start()` no hace nada si ya está activa.
 */
final class StartSession
{
    public function __construct(private readonly SessionInterface \$session)
    {
    }

    public function __invoke(Request \$request, callable \$next): mixed
    {
        \$this->session->start();

        return \$next(\$request);
    }
}
PHP;
    }

    private static function verifyCsrf(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Http\\Middleware;

use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\View\\Renderer;

/**
 * Exige un token CSRF válido en toda petición que modifique estado.
 *
 * Las peticiones seguras (GET, HEAD, OPTIONS) pasan sin comprobación.
 */
final class VerifyCsrf
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfTokenManager \$csrf,
        private readonly ?Renderer \$renderer = null
    ) {
    }

    public function __invoke(Request \$request, callable \$next): Response
    {
        if (in_array(strtoupper(\$request->method), self::SAFE_METHODS, true)) {
            return \$next(\$request);
        }

        \$token = \$request->input()['_token'] ?? null;

        if (!\$this->csrf->isValid(is_string(\$token) ? \$token : null)) {
            return \$this->rejected();
        }

        return \$next(\$request);
    }

    private function rejected(): Response
    {
        if (\$this->renderer !== null && \$this->renderer->locate('errors.419') !== null) {
            return \$this->renderer->layout('errors.419', 'layout', ['title' => 'Sesión expirada'], 419);
        }

        return Response::text('419 Token CSRF inválido. Recarga el formulario.', 419);
    }
}
PHP;
    }

    private static function authController(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers;

use Dr2g\\ErpLibs\\Auth\\AuthAudit;
use Dr2g\\ErpLibs\\Auth\\AuthManager;
use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Auth\\LoginThrottle;
use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use Dr2g\\ErpLibs\\Auth\\Session\\SessionInterface;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\View\\Renderer;
use {$app}\\Models\\User;
use {$app}\\Repositories\\UserRepository;

/**
 * Entrada y salida de la aplicación.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthManager \$auth,
        private readonly UserRepository \$users,
        private readonly Hasher \$hasher,
        private readonly CsrfTokenManager \$csrf,
        private readonly LoginThrottle \$throttle,
        private readonly AuthAudit \$audit,
        private readonly ?SessionInterface \$session = null
    ) {
    }

    public function showLogin(Request \$request): Response
    {
        return \$this->render('auth.login', [
            'title' => 'Entrar',
            'csrf' => \$this->csrf->token(),
            'email' => '',
            'error' => null,
        ]);
    }

    public function login(Request \$request): Response
    {
        \$data = \$request->input();
        \$email = trim((string) (\$data['email'] ?? ''));
        \$password = (string) (\$data['password'] ?? '');
        \$ip = \$this->clientIp(\$request);

        // Límite por IP+email y por email: frena la fuerza bruta y el rociado.
        \$limit = \$this->throttle->attempt(\$email, \$ip);

        if (!\$limit['allowed']) {
            \$this->audit->throttled(\$email, \$limit['retry_after'], \$ip);

            return \$this->render('auth.login', [
                'title' => 'Entrar',
                'csrf' => \$this->csrf->token(),
                'email' => \$email,
                'error' => 'Demasiados intentos. Espera ' . ceil(\$limit['retry_after'] / 60) . ' minuto(s).',
            ], 429);
        }

        \$user = \$email === '' ? null : \$this->users->findByEmail(\$email);

        // Mensaje y código idénticos tanto si el email no existe como si la
        // contraseña no coincide: no se revela qué usuarios existen.
        if (\$user === null || !\$user->isActive() || !\$this->hasher->verify(\$password, \$user->toArray()['password'] ?? '')) {
            \$this->audit->loginFailed(\$email, \$ip);

            return \$this->render('auth.login', [
                'title' => 'Entrar',
                'csrf' => \$this->csrf->token(),
                'email' => \$email,
                'error' => 'Credenciales incorrectas.',
            ], 422);
        }

        \$this->throttle->clear(\$email, \$ip);
        \$this->auth->login(\$user);
        \$this->csrf->rotate();
        \$this->audit->login(\$email, \$ip);

        // "Recordarme": sesión más larga (la cookie la gestiona PHP).
        if (!empty(\$data['remember'])) {
            \$this->session?->set('remember_until', time() + (int) (getenv('AUTH_REMEMBER_SECONDS') ?: 2592000));
        }

        \$target = (string) (\$data['redirect'] ?? '/admin');

        // Sólo rutas internas: evita redirecciones abiertas.
        if (!str_starts_with(\$target, '/') || str_starts_with(\$target, '//')) {
            \$target = '/admin';
        }

        return Response::redirect(\$target, 303);
    }

    /**
     * Formulario de cambio de contraseña del usuario autenticado.
     */
    public function showChangePassword(Request \$request): Response
    {
        return \$this->render('auth.password', [
            'title' => 'Cambiar contraseña',
            'csrf' => \$this->csrf->token(),
            'error' => null,
        ]);
    }

    public function changePassword(Request \$request): Response
    {
        \$user = \$request->attribute('auth_user');
        \$data = \$request->input();
        \$ip = \$this->clientIp(\$request);
        \$current = (string) (\$data['current_password'] ?? '');
        \$new = (string) (\$data['password'] ?? '');
        \$confirmation = (string) (\$data['password_confirmation'] ?? '');

        \$errors = [];

        if (!\$user instanceof User || !\$this->hasher->verify(\$current, \$user->toArray()['password'] ?? '')) {
            \$errors[] = 'La contraseña actual no es correcta.';
        }

        if (strlen(\$new) < 8) {
            \$errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if (\$new !== \$confirmation) {
            \$errors[] = 'La confirmación no coincide.';
        }

        if (\$errors !== []) {
            return \$this->render('auth.password', [
                'title' => 'Cambiar contraseña',
                'csrf' => \$this->csrf->token(),
                'error' => implode(' ', \$errors),
            ], 422);
        }

        \$this->users->update(\$user, ['password' => \$this->hasher->hash(\$new)]);
        \$this->csrf->rotate();
        \$this->audit->passwordChanged((string) \$user->toArray()['email'], \$ip);

        return Response::redirect('/admin', 303);
    }

    public function logout(Request \$request): Response
    {
        \$user = \$request->attribute('auth_user');

        if (\$user instanceof User) {
            \$this->audit->logout((string) \$user->toArray()['email'], \$this->clientIp(\$request));
        }

        \$this->auth->logout();

        return Response::redirect('/login', 303);
    }

    private function clientIp(Request \$request): string
    {
        \$forwarded = \$request->header('X-Forwarded-For');

        if (\$forwarded !== null && \$forwarded !== '') {
            return trim(explode(',', \$forwarded)[0]);
        }

        return (string) (\$_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * @param array<string, mixed> \$data
     */
    private function render(string \$view, array \$data, int \$status = 200): Response
    {
        return (new Renderer([__DIR__ . '/../views']))
            ->layout(\$view, 'layout', \$data, \$status);
    }
}
PHP;
    }

    private static function registerController(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Controllers;

use Dr2g\\ErpLibs\\Auth\\AuthManager;
use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use Dr2g\\ErpLibs\\Routing\\Request;
use Dr2g\\ErpLibs\\Routing\\Response;
use Dr2g\\ErpLibs\\Validation\\Validator;
use Dr2g\\ErpLibs\\View\\Renderer;
use {$app}\\Repositories\\UserRepository;

/**
 * Alta de usuarios. Se puede desactivar borrando su ruta.
 */
final class RegisterController
{
    public function __construct(
        private readonly UserRepository \$users,
        private readonly AuthManager \$auth,
        private readonly Hasher \$hasher,
        private readonly CsrfTokenManager \$csrf
    ) {
    }

    public function show(): Response
    {
        return \$this->form(['name' => '', 'email' => ''], []);
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

        if (\$errors === [] && (string) (\$data['password'] ?? '') !== (string) (\$data['password_confirmation'] ?? '')) {
            \$errors['password'][] = 'no coincide con la confirmación';
        }

        if (\$errors !== []) {
            return \$this->form(\$data, \$errors, 422);
        }

        \$user = \$this->users->createWithPassword(\$data, \$this->hasher);
        \$this->auth->login(\$user);
        \$this->csrf->rotate();

        return Response::redirect('/admin', 303);
    }

    /**
     * @param array<string, mixed> \$data
     * @param array<string, list<string>> \$errors
     */
    private function form(array \$data, array \$errors, int \$status = 200): Response
    {
        return (new Renderer([__DIR__ . '/../views']))->layout('auth.register', 'layout', [
            'title' => 'Crear cuenta',
            'csrf' => \$this->csrf->token(),
            'name' => (string) (\$data['name'] ?? ''),
            'email' => (string) (\$data['email'] ?? ''),
            'errors' => \$errors,
        ], \$status);
    }
}
PHP;
    }

    private static function routes(string $app): string
    {
        return <<<PHP
<?php

/*
 * Autenticación. Los servicios (sesión, csrf, auth) llegan desde bootstrap.php.
 * Si el archivo se carga antes que el que los define, se construyen aquí.
 *
 * @var \Dr2g\ErpLibs\Routing\Kernel \$kernel
 * @var \Dr2g\ErpLibs\Auth\AuthManager \$auth
 * @var \Dr2g\ErpLibs\Auth\Csrf\CsrfTokenManager \$csrf
 */

if (!isset(\$authController)) {
    \$authController = new \\{$app}\Controllers\AuthController(
        \$auth,
        new \\{$app}\Repositories\UserRepository(),
        new \Dr2g\ErpLibs\Auth\Password\Hasher(),
        \$csrf,
        \$throttle ?? \Dr2g\ErpLibs\Auth\LoginThrottle::withDefaults(),
        \$audit ?? \Dr2g\ErpLibs\Auth\AuthAudit::withDefaults(),
        \$session ?? null
    );
}

if (!isset(\$registerController)) {
    \$registerController = new \\{$app}\Controllers\RegisterController(
        new \\{$app}\Repositories\UserRepository(),
        \$auth,
        new \Dr2g\ErpLibs\Auth\Password\Hasher(),
        \$csrf
    );
}

\$kernel->router()->get('/login', \$authController->showLogin(...));
\$kernel->router()->post('/login', \$authController->login(...));
\$kernel->router()->post('/logout', \$authController->logout(...));

\$kernel->router()->get('/register', \$registerController->show(...));
\$kernel->router()->post('/register', \$registerController->store(...));

// Cambio de contraseña: requiere sesión.
\$kernel->router()->group('/password', function (\$router) use (\$authController, \$authMiddleware): void {
    \$router->get('/', \$authController->showChangePassword(...));
    \$router->post('/', \$authController->changePassword(...));
}, [\$authMiddleware]);
PHP;
    }

    private static function loginView(string $app): string
    {
        return <<<'PHP'
<?php
/**
 * Formulario de entrada.
 *
 * @var string $csrf
 * @var string $email
 * @var null|string $error
 */
?>
<h1>Entrar</h1>

<?php if ($error !== null): ?>
    <p role="alert"><strong>Error:</strong> <?= htmlspecialchars($error, ENT_QUOTES) ?></p>
<?php endif; ?>

<form method="post" action="/login">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? '/admin', ENT_QUOTES) ?>">

    <p>
        <label for="email">Email</label><br>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" required autocomplete="username">
    </p>
    <p>
        <label for="password">Contraseña</label><br>
        <input id="password" name="password" type="password" required autocomplete="current-password">
    </p>
    <p>
        <label>
            <input type="checkbox" name="remember" value="1">
            Mantener la sesión iniciada
        </label>
    </p>
    <p><button type="submit">Entrar</button></p>
</form>

<p><a href="/register">Crear una cuenta</a></p>
PHP;
    }

    private static function registerView(string $app): string
    {
        return <<<'PHP'
<?php
/**
 * Formulario de alta.
 *
 * @var string $csrf
 * @var string $name
 * @var string $email
 * @var array<string, list<string>> $errors
 */
$field = static fn (string $key): string => isset($errors[$key]) ? implode(', ', $errors[$key]) : '';
?>
<h1>Crear cuenta</h1>

<form method="post" action="/register">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <p>
        <label for="name">Nombre</label><br>
        <input id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES) ?>" required maxlength="120">
        <?php if ($field('name') !== ''): ?><span role="alert"><?= htmlspecialchars($field('name'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="email">Email</label><br>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" required>
        <?php if ($field('email') !== ''): ?><span role="alert"><?= htmlspecialchars($field('email'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="password">Contraseña</label><br>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <?php if ($field('password') !== ''): ?><span role="alert"><?= htmlspecialchars($field('password'), ENT_QUOTES) ?></span><?php endif; ?>
    </p>
    <p>
        <label for="password_confirmation">Repite la contraseña</label><br>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
    </p>
    <p><button type="submit">Crear cuenta</button></p>
</form>

<p><a href="/login">Ya tengo cuenta</a></p>
PHP;
    }

    private static function passwordView(): string
    {
        return <<<'PHP'
<?php
/**
 * Cambio de contraseña del usuario autenticado.
 *
 * @var string $csrf
 * @var null|string $error
 */
?>
<h1>Cambiar contraseña</h1>

<?php if ($error !== null): ?>
    <p role="alert"><strong>Error:</strong> <?= htmlspecialchars($error, ENT_QUOTES) ?></p>
<?php endif; ?>

<form method="post" action="/password">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <p>
        <label for="current_password">Contraseña actual</label><br>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
    </p>
    <p>
        <label for="password">Nueva contraseña</label><br>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
    </p>
    <p>
        <label for="password_confirmation">Repite la nueva contraseña</label><br>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
    </p>
    <p><button type="submit">Guardar</button> <a href="/admin">Cancelar</a></p>
</form>
PHP;
    }

    private static function forbiddenView(string $app): string
    {
        return <<<'PHP'
<?php
/**
 * Vista de acceso denegado.
 *
 * @var string $permission
 */
?>
<h1>Acceso denegado</h1>
<p>No tienes permiso para esta sección.</p>
<?php if (isset($permission)): ?>
    <p><code><?= htmlspecialchars((string) $permission, ENT_QUOTES) ?></code></p>
<?php endif; ?>
<p><a href="/">Volver al inicio</a></p>
PHP;
    }

    private static function authTest(string $app): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace AppTests;

use Dr2g\\ErpLibs\\Auth\\AuthManager;
use Dr2g\\ErpLibs\\Auth\\Csrf\\CsrfTokenManager;
use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use Dr2g\\ErpLibs\\Auth\\Session\\ArraySession;
use Dr2g\\ErpLibs\\Core\\Connection;
use Dr2g\\ErpLibs\\Core\\DatabaseManager;
use Dr2g\\ErpLibs\\Models\\Model;
use Dr2g\\ErpLibs\\Routing\\Request;
use {$app}\\Repositories\\UserRepository;
use PHPUnit\\Framework\\TestCase;

/**
 * Contrato de autenticación sobre SQLite en memoria.
 */
final class AuthTest extends TestCase
{
    private UserRepository \$users;

    private ArraySession \$session;

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
        \$this->session = new ArraySession();
    }

    public function testPasswordIsHashed(): void
    {
        \$user = \$this->users->createWithPassword([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-123',
        ], new Hasher());

        \$stored = \$user->toArray()['password'];

        self::assertNotSame('secret-123', \$stored);
        self::assertTrue((new Hasher())->verify('secret-123', \$stored));
    }

    public function testFindByEmail(): void
    {
        \$this->users->createWithPassword(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123'], new Hasher());

        self::assertNotNull(\$this->users->findByEmail('ana@example.com'));
        self::assertNull(\$this->users->findByEmail('nadie@example.com'));
        self::assertTrue(\$this->users->emailExists('ana@example.com'));
    }

    public function testLoginStoresTheUserInTheSession(): void
    {
        \$user = \$this->users->createWithPassword(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123'], new Hasher());
        \$auth = new AuthManager(\$this->session, fn (int|string \$id) => \$this->users->find((int) \$id));

        \$auth->login(\$user);

        self::assertTrue(\$auth->check());
        self::assertSame('ana@example.com', \$auth->user()->toArray()['email']);
    }

    public function testLogoutClearsTheSession(): void
    {
        \$user = \$this->users->createWithPassword(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-123'], new Hasher());
        \$auth = new AuthManager(\$this->session, fn (int|string \$id) => \$this->users->find((int) \$id));

        \$auth->login(\$user);
        \$auth->logout();

        self::assertFalse(\$auth->check());
    }

    public function testCsrfTokenIsRequiredOnWrites(): void
    {
        \$csrf = new CsrfTokenManager(\$this->session);

        self::assertFalse(\$csrf->isValid(null));
        self::assertTrue(\$csrf->isValid(\$csrf->token()));
    }
}
PHP;
    }

    private static function user(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Models;

use Dr2g\\ErpLibs\\Auth\\Authorizable;
use Dr2g\\ErpLibs\\Models\\Attributes\\Column;
use Dr2g\\ErpLibs\\Models\\Attributes\\Hidden;
use Dr2g\\ErpLibs\\Models\\Attributes\\Table;
use Dr2g\\ErpLibs\\Models\\Model;

#[Table(name: 'users')]
class User extends Model implements Authorizable
{
    #[Column(type: 'VARCHAR(120)', validation: ['required', 'max:120'])]
    public string \$name;

    #[Column(type: 'VARCHAR(180)', unique: true, validation: ['required', 'email', 'max:180'])]
    public string \$email;

    /** El hash nunca se expone al renderizar. */
    #[Hidden]
    #[Column(type: 'VARCHAR(255)')]
    public string \$password;

    #[Column(type: 'BOOLEAN', default: true)]
    public bool \$is_active = true;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$created_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$updated_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$deleted_at = null;

    public function isActive(): bool
    {
        return (bool) \$this->is_active;
    }

    public function getAuthIdentifier(): int|string
    {
        return (int) (\$this->toArray()['id'] ?? 0);
    }

    /**
     * Permisos efectivos: la unión de los de todos sus roles.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return (new \\{$app}\\Repositories\\UserRepository())->permissionNamesFor(\$this);
    }
}
PHP;
    }

    private static function role(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Models;

use Dr2g\\ErpLibs\\Models\\Attributes\\Column;
use Dr2g\\ErpLibs\\Models\\Attributes\\Table;
use Dr2g\\ErpLibs\\Models\\Model;

#[Table(name: 'roles')]
class Role extends Model
{
    #[Column(type: 'VARCHAR(60)', unique: true, validation: ['required', 'max:60'])]
    public string \$name;

    #[Column(type: 'VARCHAR(120)', validation: ['required', 'max:120'])]
    public string \$label;

    #[Column(type: 'VARCHAR(255)', nullable: true)]
    public ?string \$description = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$created_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$updated_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$deleted_at = null;
}
PHP;
    }

    private static function permission(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Models;

use Dr2g\\ErpLibs\\Models\\Attributes\\Column;
use Dr2g\\ErpLibs\\Models\\Attributes\\Table;
use Dr2g\\ErpLibs\\Models\\Model;

#[Table(name: 'permissions')]
class Permission extends Model
{
    #[Column(type: 'VARCHAR(80)', unique: true, validation: ['required', 'max:80'])]
    public string \$name;

    #[Column(type: 'VARCHAR(120)', validation: ['required', 'max:120'])]
    public string \$label;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$created_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string \$updated_at = null;
}
PHP;
    }

    private static function userRepository(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Repositories;

use Dr2g\\ErpLibs\\CLI\\Contracts\\AuthUserRepository as AuthUserRepositoryContract;
use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use Dr2g\\ErpLibs\\Models\\Model;
use Dr2g\\ErpLibs\\Repositories\\Repository;
use {$app}\\Models\\Permission;
use {$app}\\Models\\Role;
use {$app}\\Models\\User;

/**
 * Usuarios, sus roles y sus permisos efectivos.
 */
final class UserRepository extends Repository implements AuthUserRepositoryContract
{
    public const PIVOT = 'role_user';

    private const PIVOT_PERMISSIONS = 'permission_role';

    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(string \$email): ?User
    {
        \$user = User::query()->where('email', \$email)->first();

        return \$user === null ? null : User::hydrate(\$user);
    }

    public function emailExists(string \$email): bool
    {
        return User::query()->where('email', \$email)->count() > 0;
    }

    /**
     * @return list<int>
     */
    public function roleIds(User \$user): array
    {
        return \$this->relatedIds(self::PIVOT, 'user_id', 'role_id', \$user->getAuthIdentifier());
    }

    /**
     * @param list<int|string> \$roleIds
     */
    public function syncRoles(Model \$user, array \$roleIds): void
    {
        if (!\$user instanceof User) {
            throw new \\InvalidArgumentException('UserRepository sólo puede asignar roles a modelos User.');
        }

        \$this->syncMany(\$user, self::PIVOT, 'user_id', 'role_id', \$roleIds);
    }

    /**
     * Nombres de permiso efectivos del usuario (unión de todos sus roles).
     *
     * @return list<string>
     */
    public function permissionNamesFor(User \$user): array
    {
        \$roleIds = \$this->roleIds(\$user);

        if (\$roleIds === []) {
            return [];
        }

        \$placeholders = implode(', ', array_fill(0, count(\$roleIds), '?'));
        \$rows = User::connection()->select(
            'SELECT DISTINCT p.name AS name'
            . ' FROM permissions p'
            . ' INNER JOIN ' . self::PIVOT_PERMISSIONS . ' pr ON pr.permission_id = p.id'
            . ' WHERE pr.role_id IN (' . \$placeholders . ')',
            \$roleIds
        );

        return array_map(static fn (array \$row): string => (string) \$row['name'], \$rows);
    }

    /**
     * Crea un usuario con la contraseña ya hasheada.
     *
     * @param array<string, mixed> \$attributes
     */
    public function createWithPassword(array \$attributes, Hasher \$hasher): User
    {
        \$attributes['password'] = \$hasher->hash((string) (\$attributes['password'] ?? ''));

        /** @var User \$user */
        \$user = \$this->create(\$attributes);

        return \$user;
    }
}
PHP;
    }

    private static function roleRepository(string $app): string
    {
        return <<<PHP
<?php

namespace {$app}\\Repositories;

use Dr2g\\ErpLibs\\CLI\\Contracts\\AuthRoleRepository as AuthRoleRepositoryContract;
use Dr2g\\ErpLibs\\Repositories\\Repository;
use {$app}\\Models\\Role;

/**
 * Roles y sus permisos.
 */
final class RoleRepository extends Repository implements AuthRoleRepositoryContract
{
    public const PIVOT = 'permission_role';

    protected function model(): string
    {
        return Role::class;
    }

    /**
     * @return list<int>
     */
    public function permissionIds(Role \$role): array
    {
        return \$this->relatedIds(self::PIVOT, 'role_id', 'permission_id', \$this->idOf(\$role));
    }

    /**
     * @param list<int|string> \$permissionIds
     */
    public function syncPermissions(Role \$role, array \$permissionIds): void
    {
        \$this->syncMany(\$role, self::PIVOT, 'role_id', 'permission_id', \$permissionIds);
    }

    public function findByName(string \$name): ?Role
    {
        \$row = Role::query()->where('name', \$name)->first();

        return \$row === null ? null : Role::hydrate(\$row);
    }

    private function idOf(Role \$role): int
    {
        return (int) \$role->toArray()['id'];
    }
}
PHP;
    }

    private static function migration(): string
    {
        return <<<'PHP'
<?php

use Dr2g\ErpLibs\Migrations\Migration;

/**
 * Tablas de autenticación y RBAC: usuarios, roles, permisos y sus pivotes.
 */
class CreateAuthTables extends Migration
{
    public function up(): void
    {
        if ($this->connection()->getDriver() === 'sqlite') {
            $this->sqlite();

            return;
        }

        $this->schema->create('users', function ($table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema->create('roles', function ($table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema->create('permissions', function ($table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('label', 120);
            $table->timestamps();
        });

        $this->pivot('role_user', 'user_id', 'users', 'role_id', 'roles');
        $this->pivot('permission_role', 'role_id', 'roles', 'permission_id', 'permissions');
    }

    /**
     * SQLite no admite AUTOINCREMENT en el DDL del ORM: se crea a mano.
     */
    private function sqlite(): void
    {
        $execute = fn (string $sql) => $this->connection()->execute($sql);

        $execute('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $execute('CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(60) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            description VARCHAR(255) NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $execute('CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(80) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $execute('CREATE TABLE IF NOT EXISTS role_user (
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        )');

        $execute('CREATE TABLE IF NOT EXISTS permission_role (
            role_id INTEGER NOT NULL,
            permission_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        )');
    }

    private function pivot(string $table, string $owner, string $ownerTable, string $related, string $relatedTable): void
    {
        $this->connection()->execute(sprintf(
            'CREATE TABLE %s (
                %s INT NOT NULL,
                %s INT NOT NULL,
                PRIMARY KEY (%s, %s),
                FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE CASCADE,
                FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE CASCADE
            )',
            $table,
            $owner,
            $related,
            $owner,
            $related,
            $owner,
            $ownerTable,
            $related,
            $relatedTable
        ));
    }

    public function down(): void
    {
        foreach (['permission_role', 'role_user', 'permissions', 'roles', 'users'] as $table) {
            $this->schema->drop($table);
        }
    }
}
PHP;
    }

    private static function seeder(string $app): string
    {
        $permissions = '';

        foreach (self::permissionNames() as $name) {
            $label = ucfirst(str_replace(['.', '_'], [' · ', ' '], $name));
            $permissions .= "            '{$name}' => '{$label}',\n";
        }

        return <<<PHP
<?php

namespace {$app}\\Database\\Seeders;

use Dr2g\\ErpLibs\\Auth\\Password\\Hasher;
use {$app}\\Models\\Permission;
use {$app}\\Models\\Role;
use {$app}\\Repositories\\RoleRepository;
use {$app}\\Repositories\\UserRepository;

/**
 * Datos iniciales de autenticación: permisos, rol admin y usuario admin.
 *
 * Es idempotente: puede ejecutarse varias veces sin duplicar registros.
 */
final class AuthSeeder
{
    /**
     * @return array<string, string> permiso => etiqueta
     */
    public static function permissionLabels(): array
    {
        return [
{$permissions}        ];
    }

    public function run(): void
    {
        \$roleRepository = new RoleRepository();
        \$userRepository = new UserRepository();
        \$hasher = new Hasher();

        \$ids = [];

        foreach (self::permissionLabels() as \$name => \$label) {
            \$ids[\$name] = \$this->permission(\$name, \$label);
        }

        \$admin = \$roleRepository->findByName('admin')
            ?? \$roleRepository->create(['name' => 'admin', 'label' => 'Administrador']);

        \$roleRepository->syncPermissions(\$admin, array_values(\$ids));

        \$email = (string) (getenv('INSTALL_ADMIN_EMAIL') ?: 'admin@localhost');
        \$password = (string) (getenv('INSTALL_ADMIN_PASSWORD') ?: '');

        if (\$password === '' || \$userRepository->emailExists(\$email)) {
            return;
        }

        \$adminUser = \$userRepository->createWithPassword([
            'name' => (string) (getenv('INSTALL_ADMIN_NAME') ?: 'Administrador'),
            'email' => \$email,
            'password' => \$password,
        ], \$hasher);

        \$userRepository->syncRoles(\$adminUser, [(int) \$admin->toArray()['id']]);
    }

    private function permission(string \$name, string \$label): int
    {
        \$existing = Permission::query()->where('name', \$name)->first();

        if (\$existing !== null) {
            return (int) \$existing['id'];
        }

        return (int) Permission::create(['name' => \$name, 'label' => \$label])->toArray()['id'];
    }
}
PHP;
    }
}
