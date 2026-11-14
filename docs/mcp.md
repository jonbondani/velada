# Guía técnica del servidor MCP

Para quien conecta una IA o mantiene el código. La guía de uso está en el [manual](manual.md#16-conectar-una-ia-mcp) y la lista de recursos y herramientas en la [referencia](mcp-referencia.md).

## Conexión

| Dato | Valor |
| --- | --- |
| Dirección | `https://tudominio.com/mcp` (o `/index.php?r=mcp` sin direcciones amigables) |
| Transporte | HTTP con JSON-RPC 2.0. Una petición POST, una respuesta JSON. Sin sesiones ni SSE |
| Versiones del protocolo | 2025-06-18, 2025-03-26, 2024-11-05 |
| Autenticación | Cabecera `Authorization: Bearer <token>`. Alternativa: `X-API-Key: <token>` |
| Capacidades | `tools` |

Claude Code:

```
claude mcp add --transport http eventos https://tudominio.com/mcp --header "Authorization: Bearer vel_..."
```

Configuración JSON (Claude Code, Cursor y otros):

```json
{
  "mcpServers": {
    "eventos": {
      "type": "http",
      "url": "https://tudominio.com/mcp",
      "headers": { "Authorization": "Bearer vel_..." }
    }
  }
}
```

Los tokens se crean en el panel, en Inteligencia artificial > Conexiones. No hay OAuth en esta versión.

## Orden de las comprobaciones

Cada petición pasa por estos filtros, en este orden. El primero que falla corta y lo anota en el registro.

1. Freno por IP: 30 intentos rechazados en un minuto devuelven 429 sin procesar nada más.
2. Interruptor general (`ai_enabled`). Apagado: 503.
3. Cabecera `Origin` de otra web: 403.
4. Conexión sin https fuera de localhost: 403.
5. Token: existe, no revocado, no caducado, usuario activo, perfil autorizado para IA. Si no: 401.
6. Límite de peticiones por minuto de la conexión: 429.
7. Permiso de la herramienta y del recurso, con la misma función `can()` del panel. Si no: respuesta con `isError` y resultado "denegado".
8. Reglas de negocio (aforo, estados, registros en uso).

## Respuestas HTTP

| Código | Cuándo |
| --- | --- |
| 200 | Petición atendida. Los errores de una herramienta van dentro, con `isError: true` |
| 202 | Notificación recibida |
| 400 | JSON no válido |
| 401 | Token ausente o no válido |
| 403 | Origen no permitido o conexión sin https |
| 405 | Método distinto de POST |
| 429 | Demasiadas peticiones |
| 503 | Acceso de IA apagado |

## Cómo está construido

Todo el servidor está en `app/mcp.php`. No hay permisos propios del MCP: se reutilizan los del panel.

- **Recursos.** `mcp_resources()` asocia cada tabla con el permiso del panel que la protege y con sus reglas (campos obligatorios, valores admitidos, campos de solo lectura). Los campos salen de `app/schema.php`.
- **Acciones.** Las herramientas llaman a las mismas funciones de `app/logic.php` que usan las pantallas (`create_order`, `mark_paid`, `refund_order`, `checkin_scan`, `pay_supplier_invoice`, `make_settlement`, `event_set_status`). No hay lógica duplicada.
- **Usuario.** El servidor fija el usuario del token como usuario actual, así `can()`, `user()` y el registro de actividad funcionan igual que en una sesión del panel.
- **Filtro de filas.** Un partner solo ve sus eventos y lo que cuelga de ellos (`mcp_scope()`).
- **Datos protegidos.** Las tablas de `MCP_INTERNAL` no se exponen. Los campos cuyo nombre contiene pass, token, secret o hash no salen ni se pueden escribir.

## Mantener el MCP al día cuando crece la app

| Cambio en la app | Qué pasa en el MCP |
| --- | --- |
| Campo nuevo en una tabla ya expuesta | Aparece solo, en consulta y en escritura. Si debe ser de solo lectura, añadirlo a `readonly` |
| Tabla nueva | No se expone hasta clasificarla. El panel la muestra en rojo en Inteligencia artificial > Estado, y `php app/cli/mcp-doc.php` falla |
| Acción de negocio nueva | Escribirla como función en `app/logic.php`, usarla desde la pantalla y añadir una herramienta en `mcp_tools()` que llame a esa misma función |
| Perfil o permiso nuevo | Nada: el MCP lee `PERMS` |

Lista de control al añadir una funcionalidad:

1. Si hay tabla nueva: una línea en `mcp_resources()` con su permiso, o en `MCP_INTERNAL` si no debe exponerse.
2. Si hay acción nueva: función en `logic.php` y herramienta en `mcp_tools()` con su permiso.
3. Ejecutar `php app/cli/mcp-doc.php`. Debe terminar con "Cobertura completa" y regenera la referencia.
4. Comprobar en el panel que la cobertura dice "Todo clasificado".

## Tablas

- `ai_tokens`: conexiones. Guarda el hash SHA-256 del token, nunca el token.
- `ai_log`: registro. No tiene pantalla de edición ni de borrado, y el borrado de datos de Ajustes la conserva.
