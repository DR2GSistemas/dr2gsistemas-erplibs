# Registro de decisiones — dr2gsistemas/erplibs

Una entrada por decisión con contexto, alternativas y consecuencias. Las decisiones
pequeñas viven en el código con un comentario de una línea.

## ADR-001 — MySQL, y las migraciones DDL no son transaccionales

- **Contexto**: el PRD pide «migraciones transaccionales».
- **Decisión**: mantener el `beginTransaction` alrededor del batch, pero tolerar el
  commit implícito que MySQL hace con `CREATE/ALTER/DROP TABLE`
  (`Connection::commit()/rollBack()` comprueban `inTransaction()`).
- **Alternativa descartada**: migraciones por delta transaccional puro; exigiría DDL
  sintetizado y no aporta valor sobre MySQL.
- **Consecuencia**: una migración fallida puede dejar tablas creadas. El error
  emitido lo advierte explícitamente.

## ADR-002 — Una clase por archivo y PSR-4

- **Contexto**: tres regresiones de autoload en sesiones anteriores.
- **Decisión**: nunca agrupar clases en un archivo de `src/`. Los helpers
  relacionados van en su propio archivo (`Audit/*.php`, `Cache/*.php`).
- **Consecuencia**: ligeramente más archivos, autoload y PHPStan sin sorpresas.

## ADR-003 — Esconder relaciones fuera del alcance 1.0

- **Contexto**: `#[HasMany]`, `#[BelongsTo]`, `#[HasOne]`, `#[ManyToMany]` existen
  como atributos pero no se resuelven.
- **Decisión**: declararlas fuera del alcance de 1.0 y documentarlo.
- **Alternativa descartada**: implementarlas en F2; habría requerido lazy loading,
  detección de N+1 y políticas de acceso antes de tener suite.
- **Consecuencia**: `Resource` serializa relaciones solo si el llamador las pasa.

## ADR-004 — Audit sin tabla propia

- **Contexto**: el audit podría escribir en una tabla `audit_logs`.
- **Decisión**: exponer `AuditStorageInterface` y un `MemoryAuditStorage`; el
  consumidor decide el almacenamiento.
- **Consecuencia**: el ORM no impone esquema; quien quiera persistencia implementa
  una clase de ~20 líneas.

## ADR-005 — Caché aislada del modelo

- **Contexto**: se planteó invalidar la caché automáticamente en cada `save()`.
- **Decisión**: entregar `flushTags()` y dejar el cableado a `Model::cache()` en 1.1.
- **Motivo**: sin una convención de claves pactada, el ORM inventaría una y obligaría
  a migrar la caché de los usuarios al cambiar esa convención.

## ADR-006 — Baseline de PHPStan eliminado antes del release

- **Contexto**: se aceptó un baseline de 85 errores para desbloquear F1.
- **Decisión**: eliminarlo en F5. Sólo quedó `new.static` desactivado con justificación
  (los modelos son finales por diseño, `static` es necesario para el patrón).
- **Consecuencia**: PHPStan detectó dos bugs reales de `MigrationDetector`.

## ADR-007 — SQLite para benchmarks y ejemplos ejecutables

- **Contexto**: la CI y los ejemplos no siempre tienen MySQL.
- **Decisión**: `benchmarks/run.php` y `examples/run.php` usan SQLite temporal;
  la integración con MySQL queda en el job de CI (`DR2G_TEST_DSN`).
- **Consecuencia**: los números absolutos son de SQLite; las proporciones son válidas.
