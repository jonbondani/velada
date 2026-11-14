# Manual de uso

Guía completa de la plataforma de venta y gestión de eventos. Las capturas están hechas con los datos de ejemplo que carga el instalador (la marca "Maison Alba" es inventada).

## Índice

1. [Qué es y cómo se organiza](#1-qué-es-y-cómo-se-organiza)
2. [Instalación en IONOS](#2-instalación-en-ionos)
3. [Primer acceso y tutorial guiado](#3-primer-acceso-y-tutorial-guiado)
4. [Puesta en marcha: lo que hay que configurar](#4-puesta-en-marcha-lo-que-hay-que-configurar)
5. [Perfiles del equipo](#5-perfiles-del-equipo)
6. [Locales, partners y proveedores](#6-locales-partners-y-proveedores)
7. [Eventos](#7-eventos)
8. [La web pública y la compra](#8-la-web-pública-y-la-compra)
9. [Ventas y reservas](#9-ventas-y-reservas)
10. [El día del evento: accesos con QR](#10-el-día-del-evento-accesos-con-qr)
11. [Después del evento: opiniones](#11-después-del-evento-opiniones)
12. [Clientas](#12-clientas)
13. [Finanzas](#13-finanzas)
14. [Marketing y redes](#14-marketing-y-redes)
15. [Estadísticas](#15-estadísticas)
16. [Conectar una IA (MCP)](#16-conectar-una-ia-mcp)
17. [Mantenimiento y copias de seguridad](#17-mantenimiento-y-copias-de-seguridad)
18. [Dudas frecuentes](#18-dudas-frecuentes)

---

## 1. Qué es y cómo se organiza

La plataforma tiene dos caras:

- **La web pública**, que es lo que ven las clientas: portada con los próximos eventos, ficha de cada evento, reserva, entradas con código QR y encuesta de opinión.
- **El panel de gestión**, para el equipo: eventos, ventas, accesos, proveedores, finanzas, marketing y estadísticas. Se entra en `tudominio.com/acceso`.

El ciclo completo de un evento es este:

| Fase | Dónde se hace |
| --- | --- |
| Organización | Eventos (ficha, tareas), Locales, Partners |
| Montaje y personal | Pestañas Montaje y Personal del evento, Proveedores y personal |
| Promoción | Marketing y redes, pestaña Promoción del evento |
| Venta | Web pública, Ventas |
| Día del evento | Accesos QR |
| Postventa | Encuesta de opinión, Estadísticas |
| Cuentas | Finanzas (facturas, pagos, liquidaciones) |

## 2. Instalación en IONOS

Necesitas un plan de alojamiento web de IONOS con PHP 8.0 o superior y una base de datos MySQL.

1. En el panel de IONOS, entra en **Hosting > Bases de datos**, crea una base de datos MySQL y anota el nombre de host, el nombre de la base, el usuario y la contraseña.
2. En **Hosting > PHP**, comprueba que el dominio usa PHP 8.0 o superior.
3. Sube por SFTP todo el contenido del proyecto a la carpeta del dominio, o a una subcarpeta como `/eventos`. Incluye los ficheros ocultos `.htaccess`.
4. Abre la dirección en el navegador. Aparece el instalador:

![Instalador](img/01-instalador.png)

5. Rellena los datos de la base de datos, el nombre de tu marca y tu cuenta de administración. Si dejas marcada la casilla de datos de ejemplo, la plataforma arranca con eventos, ventas y estadísticas ficticias para que veas cómo funciona todo. Se borran después con un botón (ver [Mantenimiento](#17-mantenimiento-y-copias-de-seguridad)).
6. Activa el certificado SSL del dominio en IONOS. Es necesario para que funcione la cámara del lector de QR.

Las carpetas `config`, `storage` y `uploads` necesitan permiso de escritura (755 o 775). El instalador avisa en rojo si falta alguno.

> La instalación se ha probado en Apache con MariaDB en una subcarpeta, que es el entorno más parecido a IONOS, pero no en un alojamiento de IONOS real.

## 3. Primer acceso y tutorial guiado

Se entra en `tudominio.com/acceso` con el correo y la contraseña.

![Acceso](img/02-acceso.png)

La primera vez que entra cada persona, arranca solo un **recorrido guiado** que señala cada parte de la pantalla y la explica. Se avanza con "Siguiente" (o la flecha derecha del teclado) y se puede cerrar con "Saltar" o la tecla Escape.

![Bienvenida del tutorial](img/04-tutorial-bienvenida.png)

![Un paso del tutorial](img/05-tutorial-paso.png)

El recorrido se adapta al perfil: cada persona ve solo las secciones a las que tiene acceso.

### Volver a ver el tutorial

En el menú lateral, abajo, está **Ayuda y tutorial**. Desde ahí se puede repetir el recorrido general y lanzar tutoriales paso a paso de cada tarea, las veces que haga falta. Los tutoriales solo señalan y explican: no crean ni modifican nada.

![Ayuda y tutorial](img/06-ayuda.png)

| Tutorial | Qué enseña |
| --- | --- |
| Recorrido general | Dónde está cada cosa |
| Crear y publicar un evento | El formulario del evento y su ficha, hasta publicarlo |
| Cobrar y gestionar reservas | Confirmar pagos, devolver y vender a mano |
| El día del evento: control de acceso | Leer entradas con el móvil |
| Facturas, pagos y liquidaciones | La parte económica |
| Promoción y redes sociales | Planificar publicaciones y medir |
| Puesta en marcha | Lo que hay que configurar antes de vender (solo administración) |
| Controlar el acceso de IA | Encender, apagar, dar permiso y revisar el registro (solo superusuario) |

### La pantalla de inicio

![Inicio](img/03-inicio.png)

- **Arriba**: ventas del mes, entradas vendidas, lo pendiente de cobro y los próximos eventos.
- **Próximos eventos**: cada uno con las plazas vendidas. Se abre con un clic.
- **Pendiente de atender**: pagos por confirmar, facturas que vencen, tareas atrasadas y montajes sin cerrar. Cada aviso lleva directamente al sitio donde se resuelve.

## 4. Puesta en marcha: lo que hay que configurar

Todo está en **Ajustes** (solo administración).

### Cobros

![Ajustes de cobros](img/50-ajustes-cobros.png)

- **IVA habitual**: se propone al crear cada evento y se puede cambiar en cada uno. Confirma con la asesoría qué tipo corresponde.
- **Horas que se guarda una reserva sin pagar**: pasado ese tiempo, las plazas se liberan solas.
- **Transferencia y Bizum**: marca la casilla y escribe el IBAN o el teléfono. La clienta recibe las instrucciones de pago y tú confirmas el cobro a mano cuando llega.
- **Tarjeta con Stripe**: pega la clave secreta y el secreto del webhook. En Stripe hay que crear un webhook hacia `https://tudominio.com/stripe/webhook` con el evento `checkout.session.completed`.

> El pago con tarjeta está programado pero no se ha podido probar con una cuenta real de Stripe. Haz una compra de prueba con las claves de test antes de activarlo.

### Negocio y web

En **Negocio** van el nombre comercial, los datos fiscales (salen en las facturas) y los correos. El **correo remitente** debe ser una dirección real de tu dominio creada en IONOS; si no, los correos pueden acabar en spam.

En **Web pública** se cambian el titular de portada, el texto "Sobre nosotras", el enlace a Instagram y el color de marca.

![Ajustes de la web](img/51-ajustes-web.png)

### Textos legales

Hay un texto base para condiciones de compra, privacidad y aviso legal. Hay que revisarlo con la asesoría antes de empezar a vender.

## 5. Perfiles del equipo

En **Usuarios** se da de alta a cada persona con su perfil. La tabla inferior resume qué ve cada uno.

![Usuarios](img/49-usuarios.png)

| Perfil | Para quién | Qué puede hacer |
| --- | --- | --- |
| Administración | Quien lleva la plataforma | Todo, incluidos usuarios y ajustes |
| Dirección | Gerencia | Todo el negocio; consulta ajustes y estadísticas |
| Gestión de eventos | Quien organiza | Eventos, ventas, clientas, accesos y locales |
| Finanzas | Contabilidad | Ventas, proveedores, facturas, pagos y liquidaciones |
| Marketing y redes | Comunicación | Publicaciones, códigos y estadísticas |
| Proveedores y montaje | Operaciones | Eventos (montaje, tareas, personal), locales y proveedores |
| Taquilla y accesos | Quien recibe en la puerta | Leer entradas y consultar reservas |
| Partner (externo) | Quien realiza el evento | Solo sus eventos, sin cifras económicas ni datos de clientas |

**Superusuario.** La cuenta que instala la plataforma es además superusuario. Es la única que controla el acceso de IA: lo enciende y lo apaga, decide qué perfiles pueden usarlo y ve el registro. Ni siquiera otro administrador puede hacerlo, ni modificar o eliminar al superusuario. Un superusuario puede nombrar a otro desde la ficha del usuario.

No hay recuperación de contraseña por correo: si alguien la olvida, administración le asigna una nueva editando su usuario.

## 6. Locales, partners y proveedores

Conviene darlos de alta antes de crear eventos, porque después se eligen en desplegables.

**Locales**: los espacios donde se celebran los eventos, con aforo, coste de alquiler (se usa en el presupuesto de cada evento), contacto y equipamiento disponible.

![Locales](img/32-locales.png)

**Partners**: quienes realizan el evento (chefs, formadoras, marcas). El dato clave es el **porcentaje de la venta para el partner**, que se usa para calcular su liquidación. Se puede cambiar en cada evento.

![Partners](img/33-partners.png)

**Proveedores y personal**: empresas (catering, mobiliario, floristería, sonido) y personas (camareros, fotografía). Se filtran por categoría y tipo.

![Proveedores y personal](img/34-proveedores.png)

Un local, partner o proveedor que ya se ha usado no se puede eliminar: se desmarca como activo para que deje de aparecer en los desplegables.

## 7. Eventos

### El listado

![Eventos](img/07-eventos.png)

Los eventos pasan por estos estados:

| Estado | Qué significa |
| --- | --- |
| Borrador | Solo lo ve el equipo. En la web no aparece |
| Publicado | Visible en la web y a la venta |
| Ventas cerradas | No admite más reservas |
| Finalizado | Ya se celebró |
| Cancelado | No se celebra. Las devoluciones se hacen desde Ventas |

### Crear un evento

Pulsa **Nuevo evento**.

![Nuevo evento](img/08-evento-nuevo.png)

- **Nombre y frase de presentación**: lo primero que ven las clientas.
- **Empieza / Termina**: fecha y hora.
- **Local, partner y responsable interno**.
- **Aforo total**: plazas máximas a la venta. Con 0 no hay límite.
- **Foto de portada**: horizontal y de buena calidad (JPG, PNG o WebP, hasta 8 MB). Sin foto se muestra un fondo de color con la inicial.
- **Descripción** y **Qué incluye** (una línea por elemento).
- **Economía**: el IVA incluido en el precio y, si en este evento cambia, el porcentaje o el importe fijo para el partner.

Al guardar se abre la ficha del evento.

### La ficha del evento

![Resumen del evento](img/09-evento-resumen.png)

La pestaña **Resumen** muestra las entradas vendidas, los ingresos, cuántas personas han entrado y los días que faltan. Desde aquí se publica el evento, se cierran las ventas, se duplica (copia entradas, montaje, tareas y personal) o se cancela. Abajo están los botones para enviar un **recordatorio** o la **encuesta de opinión** a todas las reservas pagadas.

### Entradas

![Entradas](img/10-evento-entradas.png)

Cada evento necesita al menos un tipo de entrada para poder publicarse.

![Nueva entrada](img/10b-entrada-nueva.png)

- **Precio**: con IVA incluido. Con precio 0 la entrada es gratuita.
- **Plazas de este tipo**: con 0, se vende hasta completar el aforo del evento.
- **Personas por entrada**: normalmente 1. Para una entrada de pareja pon 2 y para una mesa completa, 8. Cada persona recibe su propio QR y el aforo descuenta todas las plazas.
- **A la venta desde / hasta**: para entradas anticipadas.

### Montaje

![Montaje](img/11-evento-montaje.png)

Todo lo que hay que preparar en el local: mobiliario, mesas, mantelería, menaje, decoración, sonido. Cada elemento lleva cantidad, coste, proveedor y estado (pendiente, pedido, confirmado, entregado, montado, recogido). El total alimenta el presupuesto.

### Tareas

![Tareas](img/12-evento-tareas.png)

En un evento nuevo, el botón **Cargar lista de tareas habitual** crea 20 tareas repartidas en cinco fases (organización, promoción, montaje, día del evento y postventa) con fechas calculadas según el día del evento. Se marcan como hechas con un clic. Las atrasadas aparecen en la pantalla de inicio.

### Personal

![Personal](img/13-evento-personal.png)

Personas y servicios contratados para ese evento, con horario, coste y estado.

### Asistentes

![Asistentes](img/14-evento-asistentes.png)

La lista de entradas emitidas, con quién ha entrado ya. Se puede descargar en una hoja de cálculo.

### Presupuesto

![Presupuesto previsto](img/15-evento-presupuesto.png)

Compara los ingresos sin IVA con los costes: alquiler del local, montaje, personal y la parte del partner. Indica cuántas entradas hacen falta para cubrir costes.

Cuando se registran facturas de proveedores asociadas al evento, el resultado pasa a calcularse con el coste real:

![Presupuesto con facturas](img/15b-presupuesto-real.png)

### Promoción

![Promoción](img/16-evento-promo.png)

- **Enlaces con seguimiento**: un enlace distinto para Instagram, Facebook, WhatsApp y newsletter. Usando el que toca en cada sitio, la plataforma sabe de dónde llega cada visita y cada venta.
- **Ventas por canal** y **publicaciones** planificadas para el evento.

## 8. La web pública y la compra

### Portada

![Portada](img/18-web-portada.png)

Muestra solo los eventos publicados con fecha futura, avisa de las últimas plazas y marca los completos. Abajo hay un formulario para suscribirse a las novedades.

### Ficha del evento y reserva

![Ficha de evento](img/20-web-evento.png)

La reserva se hace en una sola pantalla. En el móvil:

<img src="img/21-web-reserva-movil.png" width="320" alt="Reserva en el móvil">

La clienta elige cantidades, escribe nombre y correo, elige cómo paga y acepta las condiciones. Puede introducir un código promocional o pedir factura con sus datos fiscales.

Según la forma de pago:

- **Bizum o transferencia**: ve las instrucciones de pago y las recibe por correo. Las plazas quedan guardadas hasta que confirmes el cobro o venza el plazo.
- **Tarjeta (Stripe)**: paga en el momento y las entradas se emiten solas.

<img src="img/22-web-pendiente-movil.png" width="320" alt="Reserva pendiente de pago">

### Las entradas

Con el pago confirmado, la clienta recibe un correo con el enlace a sus entradas. Cada una lleva su código QR. Desde esa página puede añadir el evento al calendario, imprimir y ver su factura.

<img src="img/26-web-entradas-movil.png" width="320" alt="Entradas con QR">

## 9. Ventas y reservas

![Ventas](img/23-ventas.png)

Todas las reservas, con buscador y filtros por evento y estado. El botón **Descargar** exporta el listado.

### Confirmar un pago

Abre la reserva pendiente, escribe si quieres la referencia del cobro y pulsa **Confirmar pago recibido**.

![Reserva pendiente](img/24-reserva-pendiente.png)

Al confirmar se emiten las entradas, se genera la factura y se envía el correo a la clienta.

![Reserva pagada](img/25-reserva-pagada.png)

Desde la ficha también puedes:

- **Reenviar** las entradas o las instrucciones de pago.
- **Cancelar** una reserva pendiente (libera las plazas).
- **Devolver** una reserva pagada: anula las entradas y emite una factura rectificativa. Si se pagó con Stripe, el importe se devuelve a la tarjeta; en los demás casos hay que devolver el dinero por el mismo medio.
- Guardar **notas internas** (alergias, mesa preferida).

### Venta manual e invitaciones

Con **Nueva venta** se registra una venta hecha por teléfono o en persona, o una invitación sin coste.

![Venta manual](img/27-venta-manual.png)

## 10. El día del evento: accesos con QR

Abre **Accesos QR** en el móvil con el que se vaya a recibir a las asistentes, elige el evento y pulsa **Activar cámara**. El navegador pedirá permiso para usarla (hace falta que la web esté en https).

<img src="img/28-accesos-movil.png" width="320" alt="Entrada válida">

| Color | Significado |
| --- | --- |
| Verde | Entrada válida. Adelante |
| Naranja | La entrada ya se había usado. Indica cuándo |
| Rojo | No existe, está anulada o es de otro evento |

<img src="img/29-accesos-repetida-movil.png" width="320" alt="Entrada ya utilizada">

Si un QR no se lee, se puede escribir el código que aparece debajo, o buscar a la persona por nombre en la lista y pulsar **Dar entrada**. Una entrada marcada por error se corrige con **Deshacer**.

Varias personas pueden validar a la vez desde móviles distintos, cada una con su usuario de perfil Taquilla.

## 11. Después del evento: opiniones

Desde el Resumen del evento, **Enviar encuesta de opinión** manda un correo a cada reserva pagada. La encuesta se responde en menos de un minuto:

<img src="img/30-web-encuesta-movil.png" width="320" alt="Encuesta de opinión">

Las respuestas aparecen en la pestaña **Opiniones** del evento, con la valoración media y el índice de recomendación (NPS):

![Opiniones del evento](img/17-evento-opiniones.png)

## 12. Clientas

![Clientas](img/31-clientas.png)

Se crean solas con cada reserva o suscripción. Cada ficha guarda sus datos, notas y el historial de reservas. La casilla **Acepta recibir novedades** refleja su consentimiento; si alguien pide la baja, se desmarca.

## 13. Finanzas

### Resumen

![Finanzas](img/35-finanzas.png)

Lo cobrado y pagado en el mes, lo que se debe a proveedores (con lo vencido en rojo) y lo pendiente de liquidar a partners. La tabla inferior da el **resultado de cada evento**.

### Facturas de proveedores

![Facturas de proveedores](img/36-facturas.png)

Con **Registrar factura** se anota cada factura recibida: proveedor, evento al que corresponde, base, IVA y retención de IRPF si la lleva. El total se calcula solo. Se puede adjuntar el PDF o una foto.

Dentro de cada factura se registran los pagos, también parciales:

![Pagos de una factura](img/37-factura-pagos.png)

### Liquidaciones a partners

![Liquidaciones](img/38-liquidaciones.png)

Cuando un evento con partner ya se ha celebrado, aparece aquí con el cálculo hecho: su porcentaje sobre la venta sin IVA más el importe fijo acordado. Pulsa **Generar liquidación** y, cuando hagas el pago, **Registrar pago**.

### Pagos realizados y facturas emitidas

**Pagos realizados** es el histórico de todo lo pagado a proveedores y partners.

![Pagos](img/39-pagos.png)

**Facturas emitidas** reúne las facturas de las reservas, con la base y el IVA repercutido del trimestre. El listado se descarga para la asesoría.

![Facturas emitidas](img/40-emitidas.png)

Así ve la clienta su factura:

![Factura de clienta](img/53-factura-cliente.png)

> La plataforma numera y emite facturas, pero no está adaptada a Verifactu. Consulta con la asesoría si te basta o si conviene emitir las facturas desde su programa.

## 14. Marketing y redes

### Cuentas

![Cuentas](img/42-cuentas.png)

Da de alta Instagram y cualquier otra red o canal: Facebook, TikTok, WhatsApp, newsletter.

### Publicaciones

![Publicaciones](img/41-marketing.png)

Un calendario de lo que se va a publicar: en qué cuenta, para qué evento, con qué formato y cuándo. Cada publicación pasa por idea, en preparación, programado y publicado. Una vez publicada, se anotan el alcance, las interacciones y los clics.

La plataforma planifica y mide, pero **no publica sola en las redes**: la publicación se hace desde cada red.

### Seguidores y alcance

Anota cada semana o cada mes los seguidores de cada cuenta para ver su evolución en Estadísticas.

Opcionalmente, con el identificador de la cuenta profesional de Instagram y un token de la API de Meta, el botón **Actualizar desde Instagram** trae los seguidores y las últimas publicaciones.

> La conexión con Instagram no se ha podido probar con una cuenta real.

### Códigos promocionales

![Códigos](img/43-codigos.png)

Descuentos en porcentaje o importe fijo, para todos los eventos o uno concreto, con usos máximos y fecha de caducidad.

### Suscriptoras

Las personas que han aceptado recibir novedades. La lista se descarga para usarla en tu herramienta de envío de correos.

## 15. Estadísticas

Arriba a la derecha se elige el periodo: 30 días, 3 meses o 12 meses.

**Visión general**: ingresos, entradas, importe medio, visitantes y valoración, con los eventos y los canales que más venden.

![Visión general](img/44-estadisticas.png)

**Web**: visitas, el camino desde la primera visita hasta la reserva pagada, de dónde llegan y desde qué dispositivo. Las visitas se cuentan en la propia plataforma, sin cookies ni servicios externos.

![Web](img/45-estadisticas-web.png)

**Redes sociales**: evolución de seguidores, alcance, interacciones, ventas llegadas desde redes y las publicaciones que mejor han funcionado.

![Redes](img/46-estadisticas-redes.png)

**Eventos**: comparativa de ocupación, ingresos, asistencia y valoración, y la ocupación media según el día de la semana y el tipo de evento.

![Eventos](img/47-estadisticas-eventos.png)

**Opiniones**: valoración media, recomendación y comentarios.

![Opiniones](img/48-estadisticas-opiniones.png)

## 16. Conectar una IA (MCP)

La plataforma incluye un servidor MCP (Model Context Protocol), el estándar con el que asistentes como Claude y otros se conectan a aplicaciones. Con él, una IA puede consultar y gestionar la plataforma en nombre de una persona del equipo.

Tres reglas que no admiten excepción:

1. **La IA nunca puede más que su usuario.** Cada conexión pertenece a una persona y hereda su perfil. Lo que esa persona no puede ver o hacer en el panel, su IA tampoco. Si cambia de perfil o se desactiva su usuario, el cambio se aplica a la IA en la siguiente petición.
2. **Todo queda registrado.** Cada petición, también las rechazadas, se guarda en un registro que solo ve el superusuario.
3. **Se puede apagar al instante.** Un botón corta el acceso a todas las IA a la vez.

El acceso de IA viene **apagado** de fábrica.

### Encender, apagar y decidir quién puede (superusuario)

En el menú, **Inteligencia artificial > Estado y apagado**:

![IA apagada](img/54-ia-apagada.png)

Al encenderlo, elige qué perfiles pueden crear conexiones. Los que no estén marcados no ven esta sección ni pueden usar IA, aunque tengan un token antiguo.

![IA encendida](img/55-ia-encendida.png)

El botón rojo **Apagar la IA ahora** corta todas las conexiones en el acto. Mientras esté apagada, cualquier intento se rechaza y se anota. Ante cualquier duda de seguridad: apaga primero y revisa el registro después.

El panel **Servidor** muestra la dirección que hay que dar a la IA y la comprobación de cobertura, que debe decir "Todo clasificado".

### Crear una conexión

Cada persona crea las suyas en **Inteligencia artificial > Conexiones**:

- **Nombre**, para reconocerla después.
- **Caducidad**: 30 días, 90 días, un año o sin caducidad.
- **Solo consulta**: la IA puede leer pero no crear, cambiar ni eliminar nada. Viene marcada por defecto.

Al crearla se muestra el token **una sola vez**, con las instrucciones para conectarlo:

![Conexión creada](img/56-ia-conexion.png)

Cualquier cliente compatible con MCP por HTTP sirve. Necesita la dirección del servidor (`https://tudominio.com/mcp`) y la cabecera `Authorization: Bearer` con el token. La web debe estar en https.

El token es como una contraseña: no se comparte ni se sube a ningún sitio. En la plataforma se guarda cifrado y no se puede recuperar; si se pierde, se revoca la conexión y se crea otra.

![Conexiones](img/57-ia-conexiones.png)

Cada persona puede revocar sus conexiones. El superusuario ve las de todo el equipo, puede revocar cualquiera y dispone de **Revocar todas**.

### Qué puede hacer una IA conectada

Depende del perfil de su usuario. A grandes rasgos:

- **Consultar**: eventos, entradas, montaje, tareas, reservas, clientas, facturas, publicaciones, estadísticas.
- **Gestionar**: crear y cambiar eventos, tareas, proveedores, publicaciones, códigos promocionales.
- **Acciones**: crear una reserva, confirmar un pago, devolver, validar una entrada, registrar pagos a proveedores, generar liquidaciones, enviar recordatorios.

Las acciones usan exactamente las mismas reglas que el panel: no se vende por encima del aforo, no se publica un evento sin entradas, no se elimina nada que esté en uso.

Lo que una IA no puede hacer nunca, sea cual sea el perfil: gestionar usuarios, cambiar ajustes, ver contraseñas, tokens o claves de pago, leer su propio registro ni tocar el interruptor.

La lista completa de recursos y herramientas, y lo que ve cada perfil, está en la [referencia del MCP](mcp-referencia.md). Los detalles técnicos están en la [guía del MCP](mcp.md).

### El registro (superusuario)

![Registro de IA](img/58-ia-registro.png)

Cada línea indica cuándo, quién (usuario, perfil y conexión), qué herramienta, con qué datos, el resultado y la dirección IP. Se puede filtrar por usuario, resultado, texto y día, y descargar completo.

| Resultado | Significado |
| --- | --- |
| Correcto | La petición se atendió |
| Denegado por permisos | La IA intentó algo que su usuario no puede hacer |
| Token rechazado | Token inexistente, revocado, caducado, de un usuario inactivo o de un perfil sin permiso |
| IA apagada | Llegó una petición con el acceso apagado |
| Error | La petición no era válida o incumplía una regla (por ejemplo, vender sin plazas) |
| Límite superado | Demasiadas peticiones por minuto |
| Bloqueado | Conexión sin https o lanzada desde otra web |
| Cambio en el panel | Encendido, apagado, cambio de permisos, alta o revocación de conexiones |

El registro no se puede editar ni borrar desde la plataforma, y se conserva aunque se borren los datos desde Ajustes. Las acciones hechas por IA aparecen además en Ajustes > Actividad con el prefijo "IA:".

> Probado con el cliente oficial del protocolo y con una batería automática de permisos. No se ha probado todavía en un alojamiento de IONOS real ni con cada asistente del mercado. Los asistentes que solo aceptan conexión por OAuth, sin cabecera con token, no están cubiertos en esta versión.

## 17. Mantenimiento y copias de seguridad

![Datos](img/52-ajustes-datos.png)

- **Borrar los datos de ejemplo**: en **Ajustes > Datos**, escribe BORRAR y confirma. Se eliminan eventos, reservas, clientas, proveedores, facturas y estadísticas. Se conservan los ajustes y tu usuario.
- **Copia de seguridad**: los datos están en la base de datos de IONOS. Se exportan desde phpMyAdmin (opción Exportar). Los documentos adjuntos están en `storage/files` y las fotos en `uploads`.
- **Correos enviados** y **Actividad**: en Ajustes hay un registro de los correos que ha generado la plataforma y de las acciones importantes del equipo.
- **Actualizar la plataforma**: sube los ficheros nuevos encima de los anteriores, sin borrar `config/config.php`, `storage` ni `uploads`. Al entrar en el panel, la base de datos se actualiza sola.
- **Reinstalar desde cero**: borra `config/config.php` y vacía la base de datos.

## 18. Dudas frecuentes

**Una clienta dice que ha pagado por Bizum.** Abre Ventas, busca su reserva y pulsa "Confirmar pago recibido".

**No le ha llegado el correo con las entradas.** Abre su reserva y pulsa "Reenviar entradas por correo". También puedes abrir "Ver lo que ve ella", copiar esa dirección y enviársela por WhatsApp.

**El evento se ha llenado y quiero ampliar plazas.** Edita la ficha del evento y sube el aforo.

**Una clienta no puede venir y cede su plaza.** No hay que hacer nada: quien presente el QR entra. Puedes anotarlo en las notas de la reserva.

**Los correos llegan a spam o aparecen como "No enviado".** Revisa en Ajustes > Negocio que el correo remitente sea una dirección real de tu dominio.

**La cámara no se activa en Accesos QR.** La web debe estar en https y hay que dar permiso de cámara al navegador. Mientras tanto se puede validar escribiendo el código o desde la lista.

**Las direcciones de la web salen con `index.php?r=`.** El instalador no detectó las direcciones amigables. Funciona igual. Para activarlas, comprueba que el fichero `.htaccess` está subido y cambia `'pretty' => false` por `true` en `config/config.php`.
