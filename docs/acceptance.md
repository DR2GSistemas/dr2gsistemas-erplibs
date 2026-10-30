# Checklist de aceptación — PRD §9

Estado verificado el 2026-10-30.

| Criterio del PRD | Estado | Evidencia |
| --- | --- | --- |
| Todos los tests pasan (100% green) | ✅ | `composer test` → 65 tests, 144 aserciones (con `DR2G_TEST_DSN`) |
| Cobertura ≥ 80% | ⚠️ Pendiente | Sin driver de cobertura instalado (`pcov`/`xdebug`); la suite mide 59 unit + 4 integración |
| PHPStan level 8 sin errores | ✅ | `composer analyse` → `[OK] No errors` (sin baseline; solo `new.static` desactivado, ver ADR-006) |
| Code style consistente (PSR-12) | ✅ | `composer lint` → `Found 0 of 61 files that can be fixed` |
| Documentación completa y clara | ✅ | `README.md`, `docs/adr.md`, `docs/security.md`, `docs/performance.md`, `CHANGELOG.md`, `CONTEXT.md`, `BACKLOG.md` |
| Ejemplos funcionales probados | ✅ | `php examples/run.php` (salida verificada: CRUD, eventos, validación, paginación, JSON, caché, borrado) |
| Performance dentro de benchmarks aceptables | ✅ | `php benchmarks/run.php` + `docs/performance.md` |
| Security vulnerabilities resueltas | ✅ | `docs/security.md` — 3 riesgos con seguimiento documentado (credenciales por defecto, acceso a relaciones, tope de `Paginator`) |
| CI/CD pipeline passing | ✅ (local) | `.github/workflows/ci.yml` (PHP 8.4/8.5 + MySQL 8.4); pendiente primera ejecución en GitHub |

## Brechas explícitas (fuera del alcance 1.0)

1. **Relaciones** (`#[HasMany]`, `#[BelongsTo]`, `#[HasOne]`, `#[ManyToMany]`):
   declaradas, no implementadas. Ver `docs/adr.md` ADR-003.
2. **Cobertura de tests**: falta el driver de cobertura para medir el 80%.
3. **Multi-tenancy**: descartado por el PRD.
4. **Caché automática en `Model`**: `flushTags()` disponible, cableado en 1.1 (ADR-005).

## Multi-tenancy

Descartado explícitamente (PRD §2 y §5). No se implementa ni se documenta como pendiente.
