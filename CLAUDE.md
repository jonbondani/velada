# Reglas del proyecto

Plataforma de venta y gestión de eventos. PHP 8 y MySQL o SQLite, sin dependencias. Destino: alojamiento compartido de IONOS.

## Al añadir o cambiar una funcionalidad

Una funcionalidad no está terminada hasta que:

1. **MCP actualizado.** Tabla nueva: clasificarla en `mcp_resources()` o `MCP_INTERNAL` (`app/mcp.php`). Acción nueva: función en `app/logic.php`, usada por la pantalla y por una herramienta de `mcp_tools()`. Nunca duplicar lógica entre panel y MCP. Ejecutar `php app/cli/mcp-doc.php`: debe decir "Cobertura completa".
2. **Permisos idénticos.** Lo que un perfil no puede en el panel, tampoco por MCP. Se usa siempre `can()`. Ningún dato secreto sale por MCP.
3. **Tutorial y manual al día.** Pasos en `app/tours.php`, apartado y capturas en `docs/manual.md`.
4. **Probado** en MySQL y SQLite antes de entregar.

## Convenciones

- Esquema en `app/schema.php`; subir `APP_VER` en `app/core.php` aplica los cambios solos (`migrate()`).
- Textos de la interfaz en español, sin rayas largas.
- El acceso de IA, su registro y el interruptor son solo del superusuario (`is_super()`).
- Commits sin líneas de coautoría.
