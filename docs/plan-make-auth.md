# Plan: `make:auth` — autenticación con RBAC

## Objetivo

Generar, desde el CLI, un sistema de autenticación completo con control de acceso
basado en roles y permisos (RBAC), con vistas, controladores y middleware
coherentes con la arquitectura existente de la librería.

**Modelo:** Usuario *N:N* Rol *N:N* Permiso.
**Sesión:** `$_SESSION` nativa de PHP con regeneración de ID y CSRF.
**Alcance:** login, logout, registro opcional y panel de administración (usuarios,
roles, permisos) protegido por permisos.
**Datos:** migraciones + seeder del administrador inicial.

---

## 1. Estado actual (lo que ya existe y hay que reutilizar)

| Pieza | Estado | Decisión |
| --- | --- | --- |
| `Routing\Router`, `Route`, `Request`, `Response` | existe | se usa tal cual |
| `Routing\Router::use()` (middleware) | existe | el middleware auth se registra aquí |
| Inyección de parámetros `{id}` por nombre | existe | los controladores declaran `int $id` |
| `Models\Model` + `#[Column]` + `#[Table]` | existe | los modelos de auth lo extienden |
| `Migrations\Migration` + `Blueprint` | existe | migraciones de las tablas |
| `Validation\Validator` | existe | validación de formularios |
| `Security\SQLInjectionGuard` | existe | validar tablas/columnas de las pivotes |
| **Regla `unique` / `confirmed`** | **NO existe** | ver §3.4 |
| `View\Renderer` + `$subview()` | existe | vistas y fragmentos |
| `Repositories\Repository` | existe | `UserRepository`, `RoleRepository` |
| **Sesión / Session** | **NO existe** | crear en la librería |
| **CSRF** | **NO existe** | crear en la librería |
| **Gate / permisos** | **NO existe** | crear en la librería |
| **Hash de contraseñas** | no hay helper | usar `password_hash()` directo |
| **Traits / pivotes en el ORM** | **NO existe** | ver §3.2 (decisión clave) |

---

## 2. Diseño de datos

### 2.1 Tablas

```
users              id, name, email (unique), password, is_active,
                   created_at, updated_at, deleted_at
roles              id, name (unique), label, description,
                   created_at, updated_at, deleted_at
permissions        id, name (unique), label, created_at, updated_at
role_user          role_id, user_id        (PK compuesta)
permission_role    permission_id, role_id  (PK compuesta)
```

Reglas:
- `users.email` es `UNIQUE` e índice.
- `permissions` **no** lleva soft deletes: un permiso es código, no dato de negocio.
- Las pivotes llevan `ON DELETE CASCADE` para que borrar un rol no deje huérfanos.
- Nombres de permiso con convención `recurso.accion` (`users.view`, `roles.update`).

### 2.2 Permisos iniciales (seeder)

| Recurso | Permisos |
| --- | --- |
| users | `users.view`, `users.create`, `users.update`, `users.delete` |
| roles | `roles.view`, `roles.create`, `roles.update`, `roles.delete` |
| permisos | `permissions.view` |

El rol `admin` recibe **todos**. El usuario admin se crea desde `.env`
(`INSTALL_ADMIN_EMAIL` / `INSTALL_ADMIN_PASSWORD`), que ya existen en la plantilla.

---

## 3. Arquitectura

### 3.1 Componentes de la librería (`src/Auth/`)

```
Session/Session.php            wrapper de $_SESSION (get/set/forget/regenerate/id)
Session/SessionInterface.php   permite sustituir por una implementación de test
Csrf/CsrfTokenManager.php      token, validate, campo oculto
Csrf/CsrfException.php
Gate.php                       can(user, permiso): bool
AuthManager.php                login/logout/check/user + sesión
Middleware/Authenticate.php    exige sesión; redirige o 401 JSON
Middleware/RequirePermission.php  exige permiso; 403
Password/Hasher.php            verify/rehash (algoritmo por defecto de PHP)
```

**Por qué en la librería y no en el scaffold:** `Session`, `Csrf`, `Gate` y los
middleware son infraestructura reutilizable; duplicarlos en cada proyecto
generado sería costoso de mantener. El scaffold genera modelos, vistas y
controladores concretos.

### 3.2 Decisión clave: relaciones N:N sin ORM relacional

El ORM actual **no soporta relaciones** (no hay `hasMany`/`belongsToMany`).
Opciones evaluadas:

| Opción | Pros | Contras |
| --- | --- | --- |
| A. Implementar `belongsToMany` en el ORM | idiomático | toca el núcleo, riesgo alto, alcance enorme |
| B. Repositorios dedicados que hagan las consultas | mínimo riesgo, aislado | más código explícito |
| **C. Ampliar `Repository` con `syncMany()`/`pluckMany()` genéricos** | reutilizable, sin tocar el núcleo | requiere cuidado con validación de identificadores |

**Elegida: C**, con estos métodos añadidos a `Repository`:

```php
protected function attachMany(Model $model, string $pivot, string $ownerKey, string $relatedKey, array $ids): void
protected function syncMany(Model $model, string $pivot, string $ownerKey, string $relatedKey, array $ids): void
protected function relatedIds(string $pivot, string $ownerKey, string $relatedKey, int|string $id): list<int>
```

Todas validan los identificadores con `SQLInjectionGuard` (ya existe) antes de
interpolar tabla/columna. Los modelos **no** ganan relaciones mágicas.

### 3.3 Flujo de una petición autenticada

```
public/index.php
  └─ bootstrap.php
       ├─ Session::start()            (una vez)
       ├─ nueva Kernel
       └─ loadRoutes('src/routes')
              ├─ middleware global: StartSession + Csrf (sólo mutantes)
              ├─ /login, /logout         → AuthController
              └─ grupo /admin            → [Authenticate, RequirePermission(<ruta>)]
                     └─ UsersController / RolesController / PermissionsController
```

`Authenticate`: sin sesión → redirige a `/login` guardando la URL de retorno
(o `401` si `Accept: application/json`).
`RequirePermission`: sin permiso → `403` con la vista `errors.403`.

### 3.4 Validación: falta `unique` y `confirmed`

`Rule` sólo soporta `required`, `string`, `integer`, `numeric`, `email`, `min`,
`max` y `boolean`. El registro necesita:

- **email único**: no puede ser una regla genérica porque requiere consultar la
  BD. Se resuelve en `UserRepository::emailExists()` y el controlador compone el
  error. **No** se añade una regla con acceso a BD a `Rule` (rompería su diseño
  sin estado).
- **confirmación de contraseña**: se compara en el controlador
  (`password === password_confirmation`), no en el validador.

Alternativa descartada: registrar reglas custom con `Rule::extend()` — existe,
pero una regla `unique` necesitaría conexión; mantenerla en el repositorio es más
honesto. **Las dos comprobaciones se cubren con tests.**

---

## 4. Superficie del CLI

```
make:auth [--force] [--no-register] [--path=<proyecto>]
```

Es **un solo comando compuesto**: genera todo lo de §5 y respeta `--force`.
`--no-register` omite las rutas y vistas de registro.

Comandos auxiliares (reutilizan la infraestructura ya publicada en 1.5–1.7):

```
make:middleware Authenticate         (ya existe el comando)
make:repository UserRepository       (ya existe)
make:migration create_users_table    (ya existe)
```

`make:auth` **no** duplica esos comandos: llama internamente a las mismas
plantillas donde tiene sentido.

---

## 5. Artefactos que genera

### Librería (permanente, versionado)

| Archivo | Contenido |
| --- | --- |
| `src/Auth/Session/SessionInterface.php` | contrato |
| `src/Auth/Session/Session.php` | `start/get/set/forget/regenerate/id` |
| `src/Auth/Csrf/CsrfTokenManager.php` | `token()`, `isValid(string)`, `field()` |
| `src/Auth/Gate.php` | `can(?User, string): bool` |
| `src/Auth/AuthManager.php` | `login/check/logout/user` |
| `src/Auth/Password/Hasher.php` | `hash`, `verify` |
| `src/Auth/Middleware/Authenticate.php` | middleware |
| `src/Auth/Middleware/RequirePermission.php` | middleware |
| `src/Repositories/Repository.php` | **+** `attachMany/syncMany/relatedIds` |

### Scaffold (en el proyecto generado)

| Archivo | Notas |
| --- | --- |
| `src/Models/User.php` | usa timestamps + soft deletes (v1.7.0) |
| `src/Models/Role.php`, `src/Models/Permission.php` | |
| `src/Repositories/UserRepository.php` | por email, con roles |
| `src/Repositories/RoleRepository.php` | con permisos |
| `src/Controllers/AuthController.php` | login (GET/POST), logout, register opcional |
| `src/Controllers/Admin/UsersController.php` | index/create/store/edit/update/destroy |
| `src/Controllers/Admin/RolesController.php` | ídem, con asignación de permisos |
| `src/Controllers/Admin/PermissionsController.php` | index |
| `src/Http/Middleware/StartSession.php` | arranca la sesión |
| `src/Http/Middleware/VerifyCsrf.php` | exige token en POST |
| `migrations/..._create_auth_tables.php` | users, roles, permissions, pivotes |
| `database/seeders/AuthSeeder.php` | permisos, rol admin, usuario admin |
| `src/routes/auth.php` | login/logout/register |
| `src/routes/admin.php` | grupo protegido |
| `src/views/auth/login.php` | formulario + errores |
| `src/views/auth/register.php` | opcional |
| `src/views/admin/users/{index,form,show}.php` | |
| `src/views/admin/roles/{index,form}.php` | |
| `src/views/admin/permissions/index.php` | |
| `src/views/errors/403.php` | acceso denegado |
| `src/views/partials/{auth-nav,flash}.php` | subviews |
| `src/tests/Feature/AuthTest.php` | login, logout, 403 |
| `src/tests/Feature/RbacTest.php` | permisos por rol |

---

## 6. Seguridad (requisitos no negociables)

1. `password_hash()` / `password_verify()`; nunca MD5/SHA1.
2. Regeneración de ID de sesión tras login (fijación de sesión).
3. `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => APP_ENV==='production'])`.
4. CSRF obligatorio en todo `POST`; token comparado con `hash_equals`.
5. Mensaje de error genérico en login ("Credenciales incorrectas").
6. Sin enumeración de usuarios (mismo mensaje y tiempo similar).
7. Todo identificador SQL validado con `SQLInjectionGuard`.
8. Escape con `htmlspecialchars(..., ENT_QUOTES)` en todas las vistas.
9. El usuario admin inicial **no** nace con contraseña fija en código: la toma de `.env`.

---

## 7. Orden de implementación (por fases, con tests en cada una)

| Fase | Contenido | Test |
| --- | --- | --- |
| **1** | `Session`, `Csrf`, `Hasher` en la librería | unitarios |
| **2** | `attachMany/syncMany/relatedIds` en `Repository` | contra SQLite real |
| **3** | `Gate`, `AuthManager`, middleware | unitarios con sesión en memoria |
| **4** | Plantillas: modelos, repositorios, migración, seeder | `php -l` + generación |
| **5** | Controladores y rutas | feature con SQLite en memoria |
| **6** | Vistas (login, admin, 403, subviews) | render + escape |
| **7** | `make:auth` orquestando todo | integración end-to-end |
| **8** | Docs + CHANGELOG + verificación manual en servidor | |

Cada fase cierra con: `phpunit`, `phpstan`, `php-cs-fixer` en verde.

---

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| El ORM no tiene relaciones → consultas manuales propensas a error | API acotada en `Repository` + tests con SQLite |
| `$_SESSION` complica los tests | `SessionInterface` + `ArraySession` para tests |
| Regenerar un proyecto con `--force` pisa el panel admin | `make:auth` respeta el comportamiento existente: no sobrescribe sin `--force` |
| Tablas ya existentes en proyectos reales | migración con `IF NOT EXISTS` donde el motor lo permita |
| Ruptura con la v1.7.0 (timestamps) | los modelos usan `#[Column]` DATETIME |
| Sesión iniciada dos veces (cabeceras enviadas) | `StartSession` idempotente |

---

## 9. Fuera de alcance (explícito)

- No se implementan relaciones `belongsToMany` en el ORM.
- No hay "recordarme" (remember me) ni tokens de API.
- No hay recuperación de contraseña por email.
- No hay 2FA.
- No se toca `ApiKernel` (RBAC sólo web en esta entrega).

---

## 10. Verificación final esperada

- `make:auth` en un proyecto limpio genera todo sin errores.
- Login correcto → redirige al panel.
- Login incorrecto → 422 con mensaje genérico.
- Sin sesión en `/admin` → redirige a `/login`.
- Con rol sin permiso → 403.
- `POST` sin token CSRF → 419.
- Suite de la librería en verde + PHPStan + CS-Fixer.
