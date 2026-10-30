# Security Policy

## Versiones soportadas

| Versión | Soporte |
| --- | --- |
| 1.x | ✅ activa |
| < 1.0 | ❌ |

## Reportar una vulnerabilidad

**No abras un issue público.** Envía un correo a `security@dr2gsistemas.local`
con:

1. Versión afectada (`composer show dr2gsistemas/erplibs`).
2. Descripción del impacto y vector de ataque.
3. Pasos mínimos para reproducir (PoC si es posible).

Acuse de recibo en 72 h y una propuesta de fecha de parche. Si lo solicitas,
se te acredita en el CHANGELOG.

## Alcance

El ORM construye SQL a partir de nombres de columna/tabla y valores de usuario.
Consideramos vulnerabilidad:

- Inyección SQL a través de identificadores no validados.
- Exposición de credenciales en logs o salidas de error.
- Deserialización insegura en caché.
- Saltarse `fillable()`/`visible()` en `ApiCrud` (mass assignment o fuga de campos).

Fuera de alcance: código de la aplicación consumidora, configuración del
servidor web, credenciales por defecto en `config/database.php` (deben
sobrescribirse con variables de entorno).

## Buenas prácticas en despliegue

- No versionar `.env` ni `.env.local` (ya están en `.gitignore`).
- `APP_DEBUG=false` en producción.
- Usar un usuario de base de datos con permisos mínimos.
- Rotar `APP_KEY` solo con un plan de migración de datos cifrados.
