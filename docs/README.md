# Documentation index

| Documento | Contenido |
| --- | --- |
| [`../README.md`](../README.md) | Instalación, configuración y uso de cada subsistema |
| [`../CHANGELOG.md`](../CHANGELOG.md) | Versiones y cambios |
| [`routing.md`](routing.md) | Router HTTP: rutas, grupos, middleware, recursos RESTful |
| [`app-scaffold.md`](app-scaffold.md) | `new:app`, vistas y layout del proyecto generado |
| [`security.md`](security.md) | Checklist OWASP Top 10 |
| [`performance.md`](performance.md) | Benchmarks reproducibles |
| [`adr.md`](adr.md) | Registro de decisiones de arquitectura |
| [`acceptance.md`](acceptance.md) | Checklist de aceptación del PRD §9 |

## Cómo probar los ejemplos del README

Cada fragmento del README se ejecuta contra la configuración de `config/database.php`.
Los casos con base de datos requieren MySQL/MariaDB:

```bash
php bin/orm migrate
php -r "require 'vendor/autoload.php'; echo 'ok';"
```
