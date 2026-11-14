# Referencia del servidor MCP

Generado automáticamente con `php app/cli/mcp-doc.php` (versión 1.2.0). No editar a mano.

## Recursos

Cada recurso está protegido por el mismo permiso que su sección del panel.

| Recurso | Qué es | Sección del panel que da acceso | Se puede modificar |
| --- | --- | --- | --- |
| `eventos` | Eventos | Eventos | Sí, con permiso de gestión |
| `tipos_entrada` | Tipos de entrada de un evento | Eventos | Sí, con permiso de gestión |
| `montaje` | Montaje, mobiliario y decoración de un evento | Eventos | Sí, con permiso de gestión |
| `tareas` | Tareas de un evento | Eventos | Sí, con permiso de gestión |
| `personal_evento` | Personal y servicios contratados para un evento | Eventos | Sí, con permiso de gestión |
| `locales` | Locales | Locales | Sí, con permiso de gestión |
| `partners` | Partners que realizan los eventos | Partners | Sí, con permiso de gestión |
| `proveedores` | Proveedores y personal | Proveedores y personal | Sí, con permiso de gestión |
| `clientas` | Clientas | Clientas | Sí, con permiso de gestión |
| `reservas` | Reservas | Ventas | No, solo consulta |
| `lineas_reserva` | Líneas de cada reserva | Ventas | No, solo consulta |
| `entradas` | Entradas emitidas con su código | Ventas | No, solo consulta |
| `facturas_emitidas` | Facturas emitidas a clientas | Finanzas | No, solo consulta |
| `facturas_proveedor` | Facturas recibidas de proveedores | Finanzas | Sí, con permiso de gestión |
| `pagos` | Pagos realizados a proveedores y partners | Finanzas | No, solo consulta |
| `liquidaciones` | Liquidaciones a partners | Finanzas | No, solo consulta |
| `cuentas_redes` | Cuentas de redes sociales | Marketing y redes | Sí, con permiso de gestión |
| `publicaciones` | Publicaciones planificadas y sus resultados | Marketing y redes | Sí, con permiso de gestión |
| `metricas_redes` | Seguidores y alcance de cada cuenta por fecha | Marketing y redes | Sí, con permiso de gestión |
| `codigos_descuento` | Códigos promocionales | Marketing y redes | Sí, con permiso de gestión |
| `opiniones` | Opiniones de las asistentes | Estadísticas | No, solo consulta |
| `usuarios` | Usuarios del panel | Usuarios | No, solo consulta |
| `correos_enviados` | Registro de correos enviados | Ajustes | No, solo consulta |
| `actividad` | Registro de actividad del equipo | Ajustes | No, solo consulta |

Nunca se exponen: `settings`, `login_attempts`, `ai_tokens`, `ai_log`, `pageviews`, ni ningún campo cuyo nombre contenga pass, token, secret o hash.

## Herramientas

| Herramienta | Qué hace | Tipo |
| --- | --- | --- |
| `que_puedo_hacer` | Devuelve quién eres en la plataforma, tu perfil y la lista de recursos a los que tienes acceso, con sus campos y si puedes modificarlos. Úsala al empezar. | Consulta |
| `buscar` | Lista registros de un recurso con filtros. | Consulta |
| `obtener` | Devuelve un registro por su id. | Consulta |
| `crear` | Crea un registro. Consulta antes los campos con que_puedo_hacer. | Cambio |
| `actualizar` | Cambia campos de un registro existente. Solo se tocan los campos enviados. | Cambio |
| `eliminar` | Elimina un registro de forma definitiva. Se rechaza si el registro está en uso. Pide confirmación a la persona antes de usarla. | Cambio delicado |
| `resumen_evento` | Situación de un evento: plazas vendidas, asistencia, avance de tareas y, si tu perfil lo permite, ingresos y presupuesto. | Consulta |
| `cambiar_estado_evento` | Publica, cierra ventas, finaliza, cancela o devuelve a borrador un evento. Estados: draft, published, closed, done, cancelled. Publicar lo hace visible en la web; cancelar detiene la venta. | Cambio delicado |
| `cargar_tareas_habituales` | Añade a un evento las 20 tareas típicas, repartidas por fases y con fechas calculadas según el día del evento. | Cambio |
| `enviar_aviso_asistentes` | Envía por correo a todas las reservas pagadas de un evento un recordatorio ("recordatorio") o la encuesta de opinión ("encuesta"). Son correos reales a clientas: pide confirmación antes. | Cambio delicado |
| `crear_reserva` | Registra una venta manual o una invitación para un evento. Comprueba aforo y plazas igual que la web. Si "pagada" es verdadero se emiten las entradas y se envían por correo. | Cambio |
| `confirmar_pago` | Marca como pagada una reserva pendiente (Bizum o transferencia recibidos). Emite las entradas con QR, genera la factura y envía el correo a la clienta. | Cambio |
| `cancelar_reserva` | Cancela una reserva que aún no se ha pagado y libera sus plazas. | Cambio delicado |
| `devolver_reserva` | Devuelve una reserva pagada: anula sus entradas y emite factura rectificativa. No se puede deshacer. Pide confirmación expresa a la persona antes de usarla. | Cambio delicado |
| `reenviar_correo_reserva` | Vuelve a enviar a la clienta sus entradas (si está pagada) o las instrucciones de pago (si está pendiente). | Cambio |
| `validar_entrada` | Da entrada a una asistente a partir del código de su entrada o de la dirección de su QR. Con "deshacer" verdadero se anula una validación hecha por error. | Cambio |
| `registrar_pago_factura` | Anota un pago, total o parcial, de una factura de proveedor. Solo registra el pago: no mueve dinero. | Cambio |
| `generar_liquidacion` | Calcula y crea la liquidación de un evento con partner: su porcentaje sobre la venta sin IVA más el importe fijo acordado. | Cambio |
| `registrar_pago_liquidacion` | Anota un pago de una liquidación. Solo registra el pago: no mueve dinero. | Cambio |
| `resumen_finanzas` | Lo cobrado y pagado este mes, lo que se debe a proveedores (y lo vencido) y lo pendiente de liquidar a partners. | Consulta |
| `estadisticas` | Ingresos, reservas, visitantes de la web, ventas por evento y por canal, y valoración media en un periodo. | Consulta |
| `ver_ajustes` | Ajustes generales de la plataforma (nombre, datos fiscales, formas de cobro activas). No incluye claves ni datos secretos, y no se pueden cambiar desde aquí. | Consulta |

Cada perfil ve solo las herramientas que su permiso admite. Una conexión de solo consulta no recibe ninguna herramienta de cambio.

## Qué recursos ve cada perfil

| Perfil | Consulta | Además puede modificar |
| --- | --- | --- |
| Administración | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, reservas, lineas_reserva, entradas, facturas_emitidas, facturas_proveedor, pagos, liquidaciones, cuentas_redes, publicaciones, metricas_redes, codigos_descuento, opiniones, usuarios, correos_enviados, actividad | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, facturas_proveedor, cuentas_redes, publicaciones, metricas_redes, codigos_descuento |
| Dirección | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, reservas, lineas_reserva, entradas, facturas_emitidas, facturas_proveedor, pagos, liquidaciones, cuentas_redes, publicaciones, metricas_redes, codigos_descuento, opiniones, correos_enviados, actividad | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, facturas_proveedor, cuentas_redes, publicaciones, metricas_redes, codigos_descuento |
| Gestión de eventos | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, reservas, lineas_reserva, entradas, cuentas_redes, publicaciones, metricas_redes, codigos_descuento, opiniones | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, clientas |
| Finanzas | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores, clientas, reservas, lineas_reserva, entradas, facturas_emitidas, facturas_proveedor, pagos, liquidaciones, opiniones | partners, proveedores, facturas_proveedor |
| Marketing y redes | eventos, tipos_entrada, montaje, tareas, personal_evento, clientas, reservas, lineas_reserva, entradas, cuentas_redes, publicaciones, metricas_redes, codigos_descuento, opiniones | cuentas_redes, publicaciones, metricas_redes, codigos_descuento |
| Proveedores y montaje | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, partners, proveedores | eventos, tipos_entrada, montaje, tareas, personal_evento, locales, proveedores |
| Taquilla y accesos | eventos, tipos_entrada, montaje, tareas, personal_evento, reservas, lineas_reserva, entradas | Nada |
| Partner (externo) | eventos, tipos_entrada, montaje, tareas, personal_evento | Nada |
