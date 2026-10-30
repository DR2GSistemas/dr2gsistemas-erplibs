# Security checklist — dr2gsistemas/erplibs (OWASP Top 10)

Revisión manual del estado del código tras F4 (2026-10-30).
Cada punto indica el estado y dónde se cubre.

## A01 — Broken Access Control

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Identificadores arbitrarios en `where()`/`orderBy()` | ✅ Cubierto | `SQLInjectionGuard::assertIdentifier/assertOperator/assertDirection` |
| Acceso a relaciones sin política | ⚠️ Pendiente | Las relaciones no están implementadas (F2 quedó fuera del alcance) |
| Paginación sin límite superior | ✅ Cubierto | `Paginator` fuerza `perPage >= 1`; el control de máximo queda en la capa de aplicación |

## A02 — Cryptographic Failures

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Contraseñas en columnas de modelo | ✅ Cubierto | `AuditLogger::markSensitive()` redacta los atributos marcados |
| Credenciales en logs | ✅ Cubierto | `Connection` solo registra SQL, nunca `bindings` |
| Hashing de contraseñas | ⚠️ Fuera de alcance | Responsabilidad de la capa de aplicación (`password_hash`) |

## A03 — Injection

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| SQL injection en identificadores | ✅ Cubierto | Listas blancas de operadores y direcciones; `testRejectsInjected*` |
| SQL injection en valores | ✅ Cubierto | Todo valor va como binding de `PDO::prepare` |
| Nombres de tabla dinámicos | ✅ Cubierto | `QueryBuilder` valida la tabla en el constructor |
| Migraciones con SQL interpolado | ⚠️ Riesgo conocido | Los nombres vienen del código del desarrollador, no del usuario; documentado |

## A04 — Insecure Design

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Fuerza bruta en login | ✅ Herramienta disponible | `Audit\RateLimiter` (ventana fija) |
| Reintentos infinitos sobre BD caída | ✅ Cubierto | `Core\CircuitBreaker` |

## A05 — Security Misconfiguration

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Credenciales por defecto en `config/database.php` | ⚠️ Riesgo heredado | `root` sin contraseña; verificar antes de cualquier despliegue |
| Modo error de PDO | ✅ Cubierto | `PDO::ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false` |

## A06 — Vulnerable and Outdated Components

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Dependencias de runtime | ✅ Cero | Solo `php >= 8.4` |
| Dependencias de desarrollo | ✅ Auditadas | `composer require` reportó «No security vulnerability advisories found» al instalar PHPUnit y PHPStan |

## A07 — Identification and Authentication Failures

Fuera del alcance del ORM (capa de aplicación).

## A08 — Software and Data Integrity Failures

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Deserialización de caché | ✅ Cubierto | `unserialize(..., ['allowed_classes' => false])` en `FileCache` y `RedisCache` |

## A09 — Security Logging and Monitoring Failures

| Riesgo | Estado | Evidencia |
| --- | --- | --- |
| Sin traza de cambios | ✅ Cubierto | `AuditLogger` registra acción, modelo, id, cambios y actor |
| Sin detección de consultas lentas | ✅ Cubierto | `Connection::setSlowQueryLogging()` |

## A10 — Server-Side Request Forgery

No aplica: el ORM no hace peticiones salientes.

## Seguimiento

1. Eliminar credenciales por defecto de `config/database.php` antes de cualquier entrega.
2. Revisar el acceso por relaciones cuando se implementen.
3. Añadir límite máximo configurable al `Paginator`.
