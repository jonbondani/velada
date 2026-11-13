<?php
// Tutoriales guiados del panel. Cada paso senala un elemento de una pantalla y lo explica.
// Solo se incluyen los pasos de las secciones a las que tiene acceso el perfil de quien entra.
function tours() {
    static $T = null; if ($T !== null) return $T;
    $u = user(); $partner = $u['role'] === 'partner';
    $s = fn($route, $params, $sel, $title, $text) => ['url' => url($route, $params), 'sel' => $sel, 'title' => $title, 'text' => $text];
    $nav = fn($m, $title, $text) => can($m) ? [$s('admin', [], '.side a[data-m="' . $m . '"]', $title, $text)] : [];
    $T = [];

    /* Recorrido general: se lanza solo la primera vez que alguien entra */
    $g = [$s('admin', [], '', 'Te damos la bienvenida', 'En dos minutos te enseñamos dónde está cada cosa. Puedes saltarlo cuando quieras y volver a verlo desde "Ayuda y tutorial".')];
    if (!$partner && (can('orders') || can('finance'))) $g[] = $s('admin', [], '.kpis', 'Tu negocio de un vistazo', 'Ventas del mes, entradas vendidas, lo que queda por cobrar y los eventos que vienen.');
    $g[] = $s('admin', [], '.cols .panel', 'Próximos eventos', 'Cada evento muestra cuántas plazas lleva vendidas. Toca uno para abrir su ficha.');
    if (!$partner) $g[] = $s('admin', [], '.cols .panel:nth-child(2)', 'Lo que hay que atender', 'Pagos por confirmar, facturas que vencen, tareas atrasadas y montajes sin cerrar. Si esta lista está vacía, vas al día.');
    $g = array_merge($g,
        $nav('events', $partner ? 'Mis eventos' : 'Eventos', $partner ? 'Aquí ves los eventos que realizas, con sus entradas, montaje y tareas.' : 'El corazón de la plataforma. Cada evento reúne sus entradas, montaje, tareas, personal, asistentes, presupuesto y promoción.'),
        $nav('venues', 'Locales', 'Los espacios donde celebras los eventos, con su aforo, coste de alquiler y equipamiento.'),
        $nav('partners', 'Partners', 'Quienes realizan cada evento (chefs, formadoras, marcas) y el reparto económico acordado con cada uno.'),
        $nav('suppliers', 'Proveedores y personal', 'Catering, mobiliario, floristería, camareros, fotografía. Todo lo que contratas.'),
        $nav('orders', 'Ventas', 'Todas las reservas. Desde aquí confirmas pagos por Bizum o transferencia, haces devoluciones y vendes a mano.'),
        $nav('customers', 'Clientas', 'Tu agenda de clientas con su historial de reservas. Se rellena sola con cada compra.'),
        $nav('checkin', 'Accesos QR', 'El día del evento, abre esta pantalla en el móvil y lee las entradas con la cámara.'),
        $nav('finance', 'Finanzas', 'Facturas de proveedores, pagos, liquidaciones a partners y el resultado de cada evento.'),
        $nav('marketing', 'Marketing y redes', 'Planifica las publicaciones de Instagram y del resto de redes, crea códigos promocionales y anota resultados.'),
        $nav('stats', 'Estadísticas', 'De dónde vienen las ventas, qué eventos funcionan mejor, cómo crecen las redes y qué opinan las asistentes.'),
        $nav('users', 'Usuarios', 'Da acceso a tu equipo. Cada persona ve solo lo que corresponde a su perfil.'),
        $nav('settings', 'Ajustes', 'Datos del negocio, textos de la web, formas de cobro y textos legales.'));
    $g[] = $s('admin', [], '#helplink', 'Siempre a mano', 'Aquí puedes repetir este recorrido y ver los tutoriales paso a paso de cada tarea. ¡Listo, ya puedes empezar!');
    $T['general'] = ['title' => 'Recorrido general', 'desc' => 'Dónde está cada cosa, en dos minutos.', 'steps' => $g];

    /* Crear y publicar un evento */
    if (can('events', 'w')) {
        $st = [$s('admin/events', [], '.actions .btn.primary', 'Empieza aquí', 'Para crear un evento pulsa "Nuevo evento". Te llevamos al formulario para que lo veas (no se guardará nada).'),
            $s('admin/events', ['do' => 'new'], 'input[name=title]', 'Nombre y frase de presentación', 'Es lo primero que verán las clientas en la web. Mejor corto y apetecible.'),
            $s('admin/events', ['do' => 'new'], 'input[name=starts_at]', 'Fecha y hora', 'Indica cuándo empieza. La hora de fin es opcional.'),
            $s('admin/events', ['do' => 'new'], 'select[name=status]', 'Estado', 'Un evento nace como borrador: solo lo ve el equipo. Cuando esté listo, lo publicas y aparece en la web.'),
            $s('admin/events', ['do' => 'new'], 'select[name=venue_id]', 'Local y partner', 'Elige dónde se celebra y quién lo realiza. Se dan de alta antes en Locales y Partners.'),
            $s('admin/events', ['do' => 'new'], 'input[name=capacity]', 'Aforo', 'Las plazas máximas a la venta. La web deja de vender sola al llegar al límite.'),
            $s('admin/events', ['do' => 'new'], 'input[name=image]', 'Foto y textos', 'Una buena foto horizontal, la descripción y la lista de lo que incluye. Es lo que más ayuda a vender.'),
            $s('admin/events', ['do' => 'new'], '.form-foot', 'Guardar', 'Al guardar entras en la ficha del evento, donde se prepara todo lo demás.')];
        $eid = (int)val("SELECT id FROM events ORDER BY CASE WHEN status='published' THEN 0 ELSE 1 END, starts_at DESC");
        if ($eid) {
            $e = fn($sel, $title, $text) => $s('admin/event', ['id' => $eid], $sel, $title, $text);
            array_push($st, $e('.tabs', 'La ficha del evento', 'Este es un evento ya creado. Cada pestaña cubre una parte del trabajo.'),
                $e('.tabs a[href*="tab=entradas"]', 'Entradas', 'Crea los tipos de entrada con su precio: general, pareja, mesa completa. Sin al menos una entrada no se puede publicar.'),
                $e('.tabs a[href*="tab=montaje"]', 'Montaje', 'Mobiliario, mesas, mantelería, decoración y sonido, con su proveedor, coste y estado.'),
                $e('.tabs a[href*="tab=tareas"]', 'Tareas', 'Con un botón cargas la lista habitual de 20 tareas, repartidas por fases y con fechas ya calculadas.'),
                $e('.tabs a[href*="tab=personal"]', 'Personal', 'Camareros, fotografía, montadores. Quién viene, a qué hora y cuánto cuesta.'),
                $e('.panel .btnrow', 'Publicar', 'Cuando todo esté listo, pulsa "Publicar en la web". Desde aquí también cierras ventas, duplicas el evento o lo cancelas.'),
                $e('.dl dd:last-child', 'Enlace para compartir', 'Esta es la página pública del evento. Cópiala para Instagram, o usa los enlaces con seguimiento de la pestaña Promoción para saber de dónde llega cada venta.'));
        }
        $T['evento'] = ['title' => 'Crear y publicar un evento', 'desc' => 'Del formulario a la web, paso a paso.', 'steps' => $st];
    }

    /* Ventas */
    if (can('orders')) {
        $st = [$s('admin/orders', [], '.toolbar', 'Encuentra cualquier reserva', 'Busca por nombre, correo o número, o filtra por evento y estado.'),
            $s('admin/orders', [], '.card.table', 'Las reservas', 'Las compras de la web aparecen solas. Naranja es pendiente de pago, verde es pagada. Toca una fila para abrirla.')];
        $oid = (int)val("SELECT id FROM orders ORDER BY CASE WHEN status='pending' THEN 0 ELSE 1 END, id DESC");
        if ($oid) {
            $st[] = $s('admin/orders', ['id' => $oid], '.cols .panel', 'La ficha de la reserva', 'Aquí ves quién compró, cómo paga y de qué canal llegó.');
            if (can('orders', 'w')) $st[] = $s('admin/orders', ['id' => $oid], '.cols .panel .btnrow', 'Confirmar, reenviar o devolver', 'Cuando recibas un Bizum o una transferencia, pulsa "Confirmar pago recibido": se emiten las entradas con QR y se envían por correo. Si hay que devolver, se anulan las entradas y se genera la factura rectificativa.');
            $st[] = $s('admin/orders', ['id' => $oid], '.cols > div:last-child', 'Importe, entradas y facturas', 'El detalle de lo comprado, los códigos de cada entrada y sus facturas.');
        }
        if (can('orders', 'w')) $st[] = $s('admin/orders', [], '.actions .btn.primary', 'Venta manual e invitaciones', 'Para vender por teléfono, en persona o regalar una invitación, usa "Nueva venta".');
        $T['ventas'] = ['title' => 'Cobrar y gestionar reservas', 'desc' => 'Confirmar pagos, devolver y vender a mano.', 'steps' => $st];
    }

    /* Accesos */
    if (can('checkin') && val("SELECT COUNT(*) FROM events WHERE status IN ('published','closed','done') AND starts_at>=?", [date('Y-m-d 00:00:00', time() - 2 * 86400)])) {
        $c = fn($sel, $title, $text) => $s('admin/checkin', [], $sel, $title, $text);
        $T['accesos'] = ['title' => 'El día del evento: control de acceso', 'desc' => 'Leer entradas QR con el móvil.', 'steps' => [
            $c('.toolbar', 'Elige el evento', 'Abre esta pantalla en el móvil con el que vayas a recibir a las asistentes y elige el evento.'),
            $c('.counter', 'Cuántas han entrado', 'El contador se actualiza con cada entrada validada.'),
            $c('#startcam', 'Leer con la cámara', 'Pulsa "Activar cámara" y apunta al QR de la entrada. Verde es adelante; naranja, que ya se había usado; rojo, que no es válida.'),
            $c('#manual', 'Sin cámara', 'Si el QR no se lee, escribe el código que aparece bajo él.'),
            $c('#att', 'Lista de asistentes', 'También puedes buscar por nombre y dar entrada a mano, o deshacer una entrada marcada por error.')]];
    }

    /* Finanzas */
    if (can('finance')) {
        $f = fn($p, $sel, $title, $text) => $s('admin/finance', $p, $sel, $title, $text);
        $T['finanzas'] = ['title' => 'Facturas, pagos y liquidaciones', 'desc' => 'El dinero que entra y el que sale.', 'steps' => [
            $f([], '.kpis', 'La caja del mes', 'Lo cobrado, lo pagado, lo que debes a proveedores y lo pendiente de liquidar a partners.'),
            $f([], '.panel table', 'Resultado por evento', 'Ingresos sin IVA, costes y parte del partner de cada evento. Así sabes cuáles son rentables.'),
            $f([], '.tabs a[href*="tab=facturas"]', 'Facturas de proveedores', 'Registra cada factura recibida, adjunta el PDF y anota los pagos, también parciales. Las vencidas se marcan en rojo.'),
            $f([], '.tabs a[href*="tab=liquidaciones"]', 'Liquidaciones a partners', 'Al terminar un evento, la plataforma calcula lo que corresponde al partner según el acuerdo. Tú solo confirmas y registras el pago.'),
            $f([], '.tabs a[href*="tab=emitidas"]', 'Facturas emitidas', 'Cada reserva pagada genera su factura. Aquí las tienes todas, con el IVA del trimestre, para descargar y pasar a la asesoría.')]];
    }

    /* Marketing y estadisticas */
    if (can('marketing')) {
        $m = fn($p, $sel, $title, $text) => $s('admin/marketing', $p, $sel, $title, $text);
        $st = [$m([], '.tabs a[href*="tab=cuentas"]', 'Primero, tus cuentas', 'Da de alta Instagram y las demás redes o canales que uses.'),
            $m([], '.tabs a[href*="tab=calendario"]', 'Publicaciones', 'Planifica qué vas a publicar, en qué red, para qué evento y cuándo. Después anota el alcance y las interacciones.'),
            $m([], '.tabs a[href*="tab=metricas"]', 'Seguidores y alcance', 'Anota cada semana los seguidores de cada cuenta para ver su evolución.'),
            $m([], '.tabs a[href*="tab=codigos"]', 'Códigos promocionales', 'Crea descuentos para campañas o colaboraciones. Cada reserva guarda el código usado.')];
        if (can('stats')) { $st[] = $s('admin/stats', ['tab' => 'redes'], '.kpis', 'Qué funciona', 'En Estadísticas ves el alcance, las interacciones y las ventas que llegaron desde cada red.'); $st[] = $s('admin/stats', ['tab' => 'redes'], '.seg', 'Elige el periodo', 'Cambia entre 30 días, 3 meses o 12 meses en cualquier pestaña de estadísticas.'); }
        $T['marketing'] = ['title' => 'Promoción y redes sociales', 'desc' => 'Planificar, medir y saber qué vende.', 'steps' => $st];
    }

    /* Puesta en marcha (solo administracion) */
    if ($u['role'] === 'admin') {
        $T['ajustes'] = ['title' => 'Puesta en marcha', 'desc' => 'Lo que hay que configurar antes de vender.', 'steps' => [
            $s('admin/settings', ['tab' => 'negocio'], '.form', 'Datos del negocio', 'Nombre comercial, datos fiscales y correos. Aparecen en la web, en los correos y en las facturas.'),
            $s('admin/settings', ['tab' => 'pagos'], '.form', 'Cómo vas a cobrar', 'Activa transferencia y Bizum con tu IBAN y tu teléfono. Para tarjeta, pega aquí las claves de Stripe.'),
            $s('admin/settings', ['tab' => 'web'], '.form', 'Tu web', 'El titular de portada, el texto "Sobre nosotras", el enlace a Instagram y el color de marca.'),
            $s('admin/settings', ['tab' => 'legal'], '.form', 'Textos legales', 'Hay un texto base para condiciones, privacidad y aviso legal. Revísalo con tu asesoría.'),
            $s('admin/users', [], '.actions .btn.primary', 'Tu equipo', 'Crea un usuario para cada persona y elige su perfil.'),
            $s('admin/users', [], '.panel', 'Qué ve cada perfil', 'Esta tabla resume a qué secciones entra cada perfil.')]];
    }
    return $T;
}
