# Performance — dr2gsistemas/erplibs

Resultados de `php benchmarks/run.php` (2026-10-30, Windows, PHP 8.4.19 ZTS).

## Metodología

- **Repositorio**: SQLite en archivo temporal. Se mide el coste del ORM
  (builder, hidratación, eventos, serialización), no el del driver. En MySQL los
  tiempos absolutos cambian, las **proporciones** entre operaciones no.
- **Filas**: 1.000 por lote (`BENCH_ROWS` para cambiarlo).
- **Reproducir**:

```bash
php benchmarks/run.php          # tabla legible
php benchmarks/run.php --json   # resultados en JSON
BENCH_ROWS=10000 php benchmarks/run.php
```

## Resultados

| Benchmark | Tiempo | Operaciones |
| --- | --- | --- |
| `insert one-by-one` (1.000 filas) | 9.665 ms | ~103 inserts/s |
| `insert in transaction` (1.000 filas) | 29,31 ms | ~34.000 inserts/s |
| `all()` + hidratación (2.000 filas) | 5,15 ms | ~388.000 filas/s |
| `find(1)` × 1.000 | 151,7 ms | ~6.600 búsquedas/s |
| `where/order/limit` × 100 | 45,19 ms | ~2.200 consultas/s |
| `Paginator::offset` × 100 | 28,19 ms | ~3.500 páginas/s |
| `Paginator::cursor` × 100 | 15,02 ms | ~6.600 páginas/s |
| Serializar 1.000 modelos | 1,91 ms | ~523.000 modelos/s |

## Conclusiones

1. **Envolver los inserts en una transacción es ~330× más rápido.** Sin transacción,
   cada `save()` es un `COMMIT` implícito; es el mayor coste evitable del ORM.
2. **La hidratación no es el cuello de botella**: 2.000 filas en 5 ms.
3. **La paginación por cursor es ~1,9× más rápida** que por offset con el mismo
   tamaño de página; recomendable para listados grandes o infinitos.
4. `find()` cuesta ~150 µs por llamada, dominado por el `prepare` de PDO; para
   lotes conviene usar `whereIn()`.

## Riesgos conocidos

- Mediciones en SQLite; la validación contra MySQL está pendiente de un runner con
  base de datos dedicada.
- Sin test de memoria: la hidratación de lotes grandes no se mide aún.
