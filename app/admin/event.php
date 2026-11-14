<?php
// Ficha completa de un evento: resumen, entradas, montaje, tareas, personal, asistentes, presupuesto, promocion y opiniones
need('events');
$u = user(); $id = (int)($_GET['id'] ?? 0);
$ev = row('SELECT e.*, v.name AS vname, v.rental_cost, p.name AS pname, p.share_pct AS pshare, m.name AS mname FROM events e
           LEFT JOIN venues v ON v.id=e.venue_id LEFT JOIN partners p ON p.id=e.partner_id LEFT JOIN users m ON m.id=e.manager_id WHERE e.id=?', [$id]);
if (!$ev || ($u['role'] === 'partner' && (int)$ev['partner_id'] !== (int)$u['partner_id'])) redirect(url('admin/events'));
$w = can('events', 'w');
$tabsAll = ['resumen' => 'Resumen', 'entradas' => 'Entradas', 'montaje' => 'Montaje', 'tareas' => 'Tareas', 'personal' => 'Personal'];
if (can('orders')) $tabsAll['asistentes'] = 'Asistentes';
if (can('finance')) $tabsAll['presupuesto'] = 'Presupuesto';
if (can('marketing')) $tabsAll['promo'] = 'Promoción';
if (can('stats') || $w) $tabsAll['opiniones'] = 'Opiniones';
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'resumen';
$P = ['id' => $id, 'tab' => $tab];
$self = fn($x = []) => url('admin/event', $x + $P);
$st = event_stats($id);
$sharePct = (float)$ev['partner_share_pct'] > 0 ? (float)$ev['partner_share_pct'] : (float)$ev['pshare'];

/* ---------- Acciones ---------- */
$act = $_GET['act'] ?? '';
if (is_post() && $act !== '') {
    need('events', 'w');
    if ($act === 'status' && isset(EVENT_STATUS[$_POST['status'] ?? ''])) {
        $err = event_set_status($ev, $_POST['status']);
        if ($err) { flash($err, 'err'); redirect($self(['tab' => 'entradas'])); }
        flash($_POST['status'] === 'cancelled' ? 'Evento cancelado. Recuerda devolver las reservas pagadas desde Ventas.' : 'Estado actualizado: ' . EVENT_STATUS[$_POST['status']] . '.');
    } elseif ($act === 'remind' || $act === 'survey') {
        $n = event_notify($id, $act);
        flash($n ? ($act === 'remind' ? "Recordatorio enviado a $n reservas." : "Encuesta enviada a $n reservas.") : ($act === 'survey' ? 'La encuesta ya se había enviado a todas las reservas.' : 'No hay reservas pagadas a las que escribir.'));
    } elseif ($act === 'duplicate') {
        $n = $ev; foreach (['id', 'vname', 'rental_cost', 'pname', 'pshare', 'mname'] as $k) unset($n[$k]);
        $n['title'] .= ' (copia)'; $n['status'] = 'draft'; $n['created_at'] = now();
        $base = slugify($n['title']); $slug = $base; $i = 2;
        while (val('SELECT COUNT(*) FROM events WHERE slug=?', [$slug])) $slug = $base . '-' . $i++;
        $n['slug'] = $slug; $nid = insert('events', $n);
        foreach (rows('SELECT * FROM ticket_types WHERE event_id=?', [$id]) as $r) { unset($r['id']); $r['event_id'] = $nid; insert('ticket_types', $r); }
        foreach (rows('SELECT * FROM setup_items WHERE event_id=?', [$id]) as $r) { unset($r['id']); $r['event_id'] = $nid; $r['status'] = 'pendiente'; insert('setup_items', $r); }
        foreach (rows('SELECT * FROM tasks WHERE event_id=?', [$id]) as $r) { unset($r['id']); $r['event_id'] = $nid; $r['done'] = 0; $r['done_at'] = null; insert('tasks', $r); }
        foreach (rows('SELECT * FROM staff_assignments WHERE event_id=?', [$id]) as $r) { unset($r['id']); $r['event_id'] = $nid; $r['status'] = 'propuesto'; insert('staff_assignments', $r); }
        flash('Evento duplicado con sus entradas, montaje, tareas y personal. Revisa la fecha antes de publicarlo.');
        redirect(url('admin/events', ['do' => 'edit', 'rid' => $nid]));
    } elseif ($act === 'template') {
        event_load_tasks($ev);
        flash('Lista de tareas habitual cargada. Ajusta fechas y responsables a tu gusto.');
    } elseif ($act === 'task') {
        $t = row('SELECT * FROM tasks WHERE id=? AND event_id=?', [(int)($_GET['t'] ?? 0), $id]);
        if ($t) q('UPDATE tasks SET done=?, done_at=? WHERE id=?', [$t['done'] ? 0 : 1, $t['done'] ? null : now(), $t['id']]);
    }
    redirect($self());
}
if ($act === 'csv' && can('orders')) {
    csv_out('asistentes-' . $ev['slug'], ['Nombre', 'Entrada', 'Estado', 'Hora de entrada', 'Reserva', 'Correo', 'Teléfono', 'Código'],
        array_map(fn($r) => [$r['holder_name'], $r['type_name'], ['valid' => 'Pendiente de entrar', 'used' => 'Ha entrado', 'void' => 'Anulada'][$r['status']] ?? '', fdt($r['checked_in_at']), $r['num'], $r['email'], $r['phone'], $r['code']],
            rows('SELECT t.*, o.num, c.email, c.phone FROM tickets t JOIN orders o ON o.id=t.order_id JOIN customers c ON c.id=o.customer_id WHERE t.event_id=? ORDER BY t.holder_name', [$id])));
}

$h = ''; $actions = '';
$supOpts = fn() => pairs('SELECT id, name FROM suppliers WHERE active=1 ORDER BY name');
$sub = null;

switch ($tab) {
case 'resumen':
    $cap = (int)$ev['capacity']; $public = abs_url('evento/' . $ev['slug']);
    $h .= '<div class="kpis">' . kpi('Entradas vendidas', $st['sold'] . ($cap ? '<i> / ' . $cap . '</i>' : ''), $cap ? progress(100 * $st['sold'] / $cap) : ($st['reserved'] ? $st['reserved'] . ' reservadas sin pagar' : ''))
        . (can('orders') || can('finance') ? kpi('Ingresos cobrados', money($st['revenue']), $st['pending'] > 0 ? money($st['pending']) . ' pendiente de cobro' : '') : '')
        . kpi('Han entrado', $st['checked'] . '<i> / ' . $st['sold'] . '</i>', $st['sold'] ? progress(100 * $st['checked'] / $st['sold'], 'green') : '')
        . kpi('Faltan', max(0, (int)ceil((strtotime($ev['starts_at']) - time()) / 86400)) . '<i> días</i>', e(fecha_larga($ev['starts_at'], true))) . '</div>';
    $info = '<dl class="dl"><dt>Estado</dt><dd>' . badge(EVENT_STATUS[$ev['status']], EVENT_BADGE[$ev['status']]) . '</dd><dt>Fecha</dt><dd>' . e(ucfirst(fecha_larga($ev['starts_at'], true))) . ($ev['ends_at'] ? ' a ' . fdate($ev['ends_at'], 'H:i') . ' h' : '') . '</dd>'
        . '<dt>Local</dt><dd>' . ($ev['vname'] ? e($ev['vname']) : '<span class="mute">Sin asignar</span>') . '</dd><dt>Partner</dt><dd>' . ($ev['pname'] ? e($ev['pname']) : '<span class="mute">Sin asignar</span>') . '</dd>'
        . '<dt>Responsable</dt><dd>' . ($ev['mname'] ? e($ev['mname']) : '<span class="mute">Sin asignar</span>') . '</dd>'
        . '<dt>Página pública</dt><dd><a href="' . e($public) . '" target="_blank">' . e($public) . '</a> <button type="button" class="link" data-copy="' . e($public) . '">Copiar</button>'
        . ($ev['status'] !== 'published' ? '<br><small class="mute">Todavía no es visible: el evento no está publicado.</small>' : '') . '</dd></dl>';
    if ($w) {
        $sb = fn($s, $l, $cls = '', $conf = '') => '<form method="post" action="' . e($self(['act' => 'status'])) . '" class="inline">' . csrf_field() . '<input type="hidden" name="status" value="' . $s . '"><button class="btn ' . $cls . '"' . ($conf ? ' data-confirm="' . e($conf) . '"' : '') . '>' . $l . '</button></form>';
        $info .= '<div class="btnrow">';
        if ($ev['status'] === 'draft') $info .= $sb('published', 'Publicar en la web', 'primary');
        if ($ev['status'] === 'published') $info .= $sb('closed', 'Cerrar ventas') . $sb('draft', 'Ocultar de la web');
        if ($ev['status'] === 'closed') $info .= $sb('published', 'Reabrir ventas') . $sb('done', 'Marcar como finalizado', 'primary');
        if ($ev['status'] === 'published' && $ev['starts_at'] < now()) $info .= $sb('done', 'Marcar como finalizado', 'primary');
        if (in_array($ev['status'], ['done', 'cancelled'])) $info .= $sb('draft', 'Volver a borrador');
        $info .= btn('Editar ficha', url('admin/events', ['do' => 'edit', 'rid' => $id])) . post_btn('Duplicar', $self(['act' => 'duplicate']));
        if (!in_array($ev['status'], ['done', 'cancelled'])) $info .= $sb('cancelled', 'Cancelar evento', 'danger', '¿Cancelar este evento? Dejará de venderse. Las devoluciones se gestionan desde Ventas.');
        $info .= '</div>';
    }
    $h .= '<div class="cols">' . panel('Datos del evento', $info);
    $tt = '<table class="plain"><thead><tr><th>Entrada</th><th class="r">Precio</th><th class="r">Vendidas</th></tr></thead><tbody>';
    foreach (rows('SELECT * FROM ticket_types WHERE event_id=? ORDER BY sort, id', [$id]) as $r) {
        $sold = (int)val("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.ticket_type_id=? AND o.status='paid'", [$r['id']]);
        $tt .= '<tr><td>' . e($r['name']) . ($r['active'] ? '' : ' ' . badge('No disponible')) . '</td><td class="r">' . money($r['price']) . '</td><td class="r">' . $sold . ((int)$r['quota'] ? ' / ' . (int)$r['quota'] : '') . '</td></tr>';
    }
    $tt .= '</tbody></table>';
    $tk = '';
    foreach (PHASES as $pk => $pl) {
        $r = row('SELECT COUNT(*) AS n, COALESCE(SUM(done),0) AS d FROM tasks WHERE event_id=? AND phase=?', [$id, $pk]);
        if ($r['n']) $tk .= '<div class="phase"><span>' . e($pl) . '</span>' . progress(100 * $r['d'] / $r['n'], 'green') . '<em>' . (int)$r['d'] . '/' . (int)$r['n'] . '</em></div>';
    }
    $h .= '<div>' . panel('Entradas', $tt) . panel('Avance de tareas', $tk ?: '<div class="nochart">Sin tareas todavía. <a href="' . e($self(['tab' => 'tareas'])) . '">Ir a Tareas</a></div>') . '</div></div>';
    if ($w && $st['sold']) $h .= panel('Comunicación con asistentes', '<p class="mute">Los mensajes salen por correo a todas las reservas pagadas de este evento.</p><div class="btnrow">'
        . post_btn('Enviar recordatorio', $self(['act' => 'remind']), '', '¿Enviar ahora un recordatorio por correo a todas las reservas pagadas?')
        . post_btn('Enviar encuesta de opinión', $self(['act' => 'survey']), '', '¿Enviar ahora la encuesta de opinión a quienes aún no la han recibido?') . '</div>');
    break;

case 'entradas':
    $sub = crud(['table' => 'ticket_types', 'route' => 'admin/event', 'params' => $P, 'scope' => ['event_id' => $id], 'perm' => 'events', 'readonly' => !$w, 'singular' => 'tipo de entrada', 'new' => 'Añadir entrada', 'order' => 'sort, id',
        'empty' => 'Crea al menos un tipo de entrada para poder vender: general, pareja, anticipada…',
        'fields' => [
            'name' => ['label' => 'Nombre', 'req' => 1, 'list' => 1, 'ph' => 'Entrada general'],
            'price' => ['label' => 'Precio (IVA incluido)', 'type' => 'money', 'req' => 1, 'list' => 1, 'short' => 'Precio'],
            'description' => ['label' => 'Aclaración breve', 'full' => 1, 'ph' => 'Opcional. Por ejemplo: incluye copa de bienvenida'],
            'quota' => ['label' => 'Plazas de este tipo', 'type' => 'number', 'list' => 1, 'short' => 'Plazas', 'help' => '0 = hasta completar el aforo del evento.'],
            'seats' => ['label' => 'Personas por entrada', 'type' => 'number', 'default' => 1, 'help' => 'Normalmente 1. Pon 2 para una entrada de pareja u 8 para una mesa completa: cada persona recibe su propio QR.'],
            'max_per_order' => ['label' => 'Máximo por reserva', 'type' => 'number', 'default' => 6],
            'sales_start' => ['label' => 'A la venta desde', 'type' => 'datetime', 'help' => 'Vacío = desde ya.'],
            'sales_end' => ['label' => 'A la venta hasta', 'type' => 'datetime', 'help' => 'Vacío = hasta el comienzo del evento.'],
            'sort' => ['label' => 'Orden en la web', 'type' => 'number'],
            'active' => ['label' => 'Disponible para la venta', 'type' => 'check', 'default' => 1, 'list' => 1, 'short' => 'Disponible'],
        ],
        'cols' => ['Vendidas' => fn($r) => '<span class="n">' . tt_sold($r['id']) . '</span>'],
        'validate' => function (array &$d, $rec) { $d['seats'] = max(1, (int)$d['seats']); return null; },
        'before_delete' => function ($r) { if (tt_sold($r['id']) || val('SELECT COUNT(*) FROM order_items WHERE ticket_type_id=?', [$r['id']])) throw new RuntimeException('Ya hay reservas con esta entrada. Desmarca "Disponible para la venta" en lugar de eliminarla.'); },
    ]);
    break;

case 'montaje':
    $sub = crud(['table' => 'setup_items', 'route' => 'admin/event', 'params' => $P, 'scope' => ['event_id' => $id], 'perm' => 'events', 'readonly' => !$w, 'singular' => 'elemento de montaje', 'new' => 'Añadir elemento', 'order' => 'category, name', 'filters' => ['status'],
        'intro' => 'Todo lo que hay que preparar en el local: mobiliario, mesas, mantelería, decoración, sonido… con su proveedor, coste y estado.',
        'fields' => [
            'name' => ['label' => 'Elemento', 'req' => 1, 'list' => 1, 'ph' => 'Mesa imperial 3 m, mantel de lino, centro floral…'],
            'category' => ['label' => 'Categoría', 'type' => 'select', 'options' => fn() => opts(SETUP_CATS), 'list' => 1],
            'qty' => ['label' => 'Cantidad', 'type' => 'number', 'default' => 1, 'list' => 1, 'short' => 'Cant.'],
            'unit_cost' => ['label' => 'Coste por unidad (sin IVA)', 'type' => 'money', 'short' => 'Coste ud.'],
            'supplier_id' => ['label' => 'Proveedor', 'type' => 'select', 'options' => $supOpts, 'list' => 1],
            'status' => ['label' => 'Estado', 'type' => 'select', 'req' => 1, 'options' => SETUP_STATUS, 'default' => 'pendiente', 'list' => 1, 'badge' => SETUP_BADGE],
            'due_at' => ['label' => 'Entrega o montaje previsto', 'type' => 'datetime'],
            'notes' => ['label' => 'Notas', 'type' => 'textarea', 'rows' => 3],
        ],
        'cols' => ['Coste' => fn($r) => '<span class="n">' . money($r['qty'] * $r['unit_cost']) . '</span>'],
        'footer' => fn($d) => '<tr><td colspan="5"><b>Coste total previsto de montaje</b></td><td><b>' . money(val('SELECT COALESCE(SUM(qty*unit_cost),0) FROM setup_items WHERE event_id=?', [$GLOBALS['id']])) . '</b></td></tr>',
    ]);
    break;

case 'tareas':
    $sub = crud(['table' => 'tasks', 'route' => 'admin/event', 'params' => $P, 'scope' => ['event_id' => $id], 'perm' => 'events', 'readonly' => !$w, 'singular' => 'tarea', 'new' => 'Añadir tarea', 'order' => 'done, due_date IS NULL, due_date, id', 'filters' => ['phase'],
        'fields' => [
            'title' => ['label' => 'Tarea', 'req' => 1, 'list' => 1, 'full' => 1],
            'phase' => ['label' => 'Fase', 'type' => 'select', 'req' => 1, 'options' => PHASES, 'default' => 'organizacion', 'list' => 1],
            'due_date' => ['label' => 'Fecha límite', 'type' => 'date', 'list' => 1, 'short' => 'Para el'],
            'assignee_id' => ['label' => 'Responsable', 'type' => 'select', 'options' => fn() => pairs("SELECT id, name FROM users WHERE active=1 AND role<>'partner' ORDER BY name"), 'list' => 1],
            'notes' => ['label' => 'Notas', 'type' => 'textarea', 'rows' => 3],
        ],
        'cols' => ['Hecha' => fn($r) => $w ? post_btn($r['done'] ? '✓ Hecha' : 'Marcar hecha', $self(['act' => 'task', 't' => $r['id']]), 'sm ' . ($r['done'] ? 'ok' : '')) : ($r['done'] ? badge('Hecha', 'green') : badge('Pendiente', 'orange'))],
    ]);
    if ($w && $sub['mode'] === 'list' && !val('SELECT COUNT(*) FROM tasks WHERE event_id=?', [$id])) $sub['html'] = '<div class="card panel"><h2>¿Empezamos con la lista habitual?</h2><p class="mute">Carga de una vez las 20 tareas típicas de un evento, repartidas por fases y con fechas calculadas según el día del evento.</p>' . post_btn('Cargar lista de tareas habitual', $self(['act' => 'template']), 'primary') . '</div>' . $sub['html'];
    break;

case 'personal':
    $sub = crud(['table' => 'staff_assignments', 'route' => 'admin/event', 'params' => $P, 'scope' => ['event_id' => $id], 'perm' => 'events', 'readonly' => !$w, 'singular' => 'persona o servicio', 'new' => 'Añadir personal', 'order' => 'start_at IS NULL, start_at, id',
        'intro' => 'Personas y servicios contratados para este evento: camareros, azafatas, fotografía, montadores, música…',
        'fields' => [
            'supplier_id' => ['label' => 'Quién', 'type' => 'select', 'req' => 1, 'options' => $supOpts, 'list' => 1, 'help' => 'Se elige de Proveedores y personal.'],
            'job' => ['label' => 'Función en el evento', 'req' => 1, 'list' => 1, 'short' => 'Función', 'ph' => 'Camarera, fotógrafo, montaje…'],
            'start_at' => ['label' => 'Entrada', 'type' => 'datetime', 'list' => 1],
            'end_at' => ['label' => 'Salida', 'type' => 'datetime'],
            'cost' => ['label' => 'Coste acordado (sin IVA)', 'type' => 'money', 'list' => 1, 'short' => 'Coste'],
            'status' => ['label' => 'Estado', 'type' => 'select', 'req' => 1, 'options' => ['propuesto' => 'Propuesto', 'confirmado' => 'Confirmado', 'realizado' => 'Realizado'], 'default' => 'propuesto', 'list' => 1, 'badge' => ['propuesto' => 'orange', 'confirmado' => 'green', 'realizado' => 'gray']],
            'notes' => ['label' => 'Notas', 'type' => 'textarea', 'rows' => 3],
        ],
    ]);
    break;

case 'asistentes':
    $list = rows('SELECT t.*, o.num, o.id AS oid, c.email, c.phone FROM tickets t JOIN orders o ON o.id=t.order_id JOIN customers c ON c.id=o.customer_id WHERE t.event_id=? ORDER BY t.holder_name, t.id', [$id]);
    $actions = btn('Descargar lista', $self(['act' => 'csv'])) . (can('checkin', 'w') ? btn('Abrir control de acceso', url('admin/checkin', ['ev' => $id]), 'primary') : '');
    if (!$list) { $h .= '<div class="empty"><b>Todavía no hay entradas emitidas.</b><br>Aparecerán aquí en cuanto se confirme la primera reserva.</div>'; break; }
    $h .= '<div class="card table"><table><thead><tr><th>Asistente</th><th>Entrada</th><th>Estado</th><th>Reserva</th><th>Contacto</th></tr></thead><tbody>';
    foreach ($list as $r) {
        $b = $r['status'] === 'used' ? badge('Ha entrado ' . fdate($r['checked_in_at'], 'H:i'), 'green') : ($r['status'] === 'void' ? badge('Anulada', 'red') : badge('Pendiente de entrar'));
        $h .= '<tr data-href="' . e(url('admin/orders', ['id' => $r['oid']])) . '"><td><a class="lead" href="' . e(url('admin/orders', ['id' => $r['oid']])) . '">' . e($r['holder_name']) . '</a></td><td>' . e($r['type_name']) . '</td><td>' . $b . '</td><td>' . e($r['num']) . '</td><td class="mute">' . e($r['email']) . ($r['phone'] ? ' · ' . e($r['phone']) : '') . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    break;

case 'presupuesto':
    $pct = (float)$ev['tax_pct']; $base = round($st['revenue'] / (1 + $pct / 100), 2);
    $venue = (float)$ev['rental_cost']; $setup = (float)val('SELECT COALESCE(SUM(qty*unit_cost),0) FROM setup_items WHERE event_id=?', [$id]); $staff = (float)val('SELECT COALESCE(SUM(cost),0) FROM staff_assignments WHERE event_id=?', [$id]);
    $partner = round($base * $sharePct / 100 + (float)$ev['partner_fee'], 2);
    $invs = rows('SELECT si.*, s.name AS sname FROM supplier_invoices si LEFT JOIN suppliers s ON s.id=si.supplier_id WHERE si.event_id=? ORDER BY si.issued_at', [$id]);
    $real = array_sum(array_map(fn($i) => (float)$i['base'], $invs)); $plan = $venue + $setup + $staff;
    $costs = $invs ? max($real, 0) : $plan; $result = $base - $costs - $partner;
    $h .= '<div class="kpis">' . kpi('Ingresos sin IVA', money($base), money($st['revenue']) . ' cobrados con IVA') . kpi($invs ? 'Costes según facturas' : 'Costes previstos', money($costs), $invs ? 'Previsto: ' . money($plan) : 'Aún sin facturas registradas')
        . kpi('Para el partner', money($partner), ($sharePct ? num($sharePct, 0) . ' % de la venta' : '') . ((float)$ev['partner_fee'] ? ' + ' . money($ev['partner_fee']) . ' fijos' : '') ?: 'Sin reparto definido')
        . kpi('Resultado', '<span class="' . ($result >= 0 ? 'pos' : 'neg') . '">' . money($result) . '</span>', $base > 0 ? 'Margen del ' . num(100 * $result / $base, 0) . ' %' : '') . '</div>';
    $t = '<table class="plain"><thead><tr><th>Concepto</th><th class="r">Previsto</th></tr></thead><tbody>'
        . '<tr><td>Alquiler del local' . ($ev['vname'] ? ' <span class="mute">' . e($ev['vname']) . '</span>' : '') . '</td><td class="r">' . money($venue) . '</td></tr>'
        . '<tr><td><a href="' . e($self(['tab' => 'montaje'])) . '">Montaje y decoración</a></td><td class="r">' . money($setup) . '</td></tr>'
        . '<tr><td><a href="' . e($self(['tab' => 'personal'])) . '">Personal y servicios</a></td><td class="r">' . money($staff) . '</td></tr></tbody><tfoot><tr><td><b>Total previsto</b></td><td class="r"><b>' . money($plan) . '</b></td></tr></tfoot></table>';
    $avg = (float)val('SELECT AVG(price) FROM ticket_types WHERE event_id=? AND active=1 AND price>0', [$id]);
    if ($avg > 0 && $plan + (float)$ev['partner_fee'] > 0) {
        $net = ($avg / (1 + $pct / 100)) * (1 - $sharePct / 100);
        if ($net > 0) $t .= '<p class="mute">Con un precio medio de ' . money($avg) . ', hacen falta unas <b>' . (int)ceil(($plan + (float)$ev['partner_fee']) / $net) . ' entradas</b> para cubrir costes.</p>';
    }
    $f = '';
    if ($invs) {
        $f = '<table class="plain"><thead><tr><th>Proveedor</th><th>Concepto</th><th class="r">Base</th><th>Estado</th></tr></thead><tbody>';
        foreach ($invs as $i) $f .= '<tr><td><a href="' . e(url('admin/finance', ['tab' => 'facturas', 'do' => 'edit', 'rid' => $i['id']])) . '">' . e($i['sname'] ?: 'Sin proveedor') . '</a></td><td>' . e($i['concept']) . '</td><td class="r">' . money($i['base']) . '</td><td>' . ($i['status'] === 'paid' ? badge('Pagada', 'green') : badge('Pendiente', 'orange')) . '</td></tr>';
        $f .= '</tbody><tfoot><tr><td colspan="2"><b>Total facturado</b></td><td class="r"><b>' . money($real) . '</b></td><td></td></tr></tfoot></table>';
    } else $f = '<div class="nochart">Cuando registres facturas de proveedores asociadas a este evento, aparecerán aquí y el resultado se calculará con el coste real.</div>';
    $h .= '<div class="cols">' . panel('Costes previstos', $t) . panel('Facturas de proveedores', $f . (can('finance', 'w') ? '<p>' . btn('Registrar factura', url('admin/finance', ['tab' => 'facturas', 'do' => 'new', 'ev' => $id])) . '</p>' : '')) . '</div>';
    break;

case 'promo':
    $views = (int)val("SELECT COUNT(*) FROM pageviews WHERE event_id=? AND kind='view'", [$id]); $buyers = (int)val("SELECT COUNT(*) FROM orders WHERE event_id=? AND status='paid'", [$id]);
    $h .= '<div class="kpis">' . kpi('Visitas a la página', num($views)) . kpi('Reservas pagadas', num($buyers)) . kpi('Conversión', $views ? num(100 * $buyers / $views, 1) . ' %' : '<span class="mute">Sin datos</span>', 'reservas por cada 100 visitas') . '</div>';
    $l = '<p class="mute">Usa estos enlaces en cada red. Así sabrás de dónde llega cada visita y cada venta.</p><div class="links">';
    foreach (['instagram', 'facebook', 'whatsapp', 'newsletter'] as $pf) {
        $lk = abs_url('evento/' . $ev['slug'], ['utm_source' => $pf, 'utm_medium' => $pf === 'newsletter' ? 'email' : 'social', 'utm_campaign' => $ev['slug']]);
        $l .= '<div><b>' . PLATFORMS[$pf] . '</b><input readonly value="' . e($lk) . '" onclick="this.select()"><button type="button" class="btn sm" data-copy="' . e($lk) . '">Copiar</button></div>';
    }
    $l .= '</div>';
    $ch = [];
    foreach (rows("SELECT utm_source, SUM(total) AS t FROM orders WHERE event_id=? AND status='paid' GROUP BY utm_source", [$id]) as $r) { $k = channel_label($r['utm_source']); $ch[$k] = ($ch[$k] ?? 0) + (float)$r['t']; }
    $h .= '<div class="cols">' . panel('Enlaces con seguimiento', $l) . panel('Ventas por canal', hbars($ch, 'money')) . '</div>';
    $posts = rows('SELECT sp.*, a.platform, a.handle FROM social_posts sp LEFT JOIN social_accounts a ON a.id=sp.account_id WHERE sp.event_id=? ORDER BY sp.scheduled_at', [$id]);
    $pt = '';
    if ($posts) {
        $pt = '<table class="plain"><thead><tr><th>Publicación</th><th>Red</th><th>Fecha</th><th>Estado</th><th class="r">Alcance</th><th class="r">Clics</th></tr></thead><tbody>';
        foreach ($posts as $r) $pt .= '<tr><td><a href="' . e(url('admin/marketing', ['tab' => 'calendario', 'do' => 'edit', 'rid' => $r['id']])) . '">' . e($r['title']) . '</a></td><td>' . e(PLATFORMS[$r['platform']] ?? '') . '</td><td>' . fdt($r['scheduled_at']) . '</td><td>' . badge(POST_STATUS[$r['status']] ?? $r['status'], POST_BADGE[$r['status']] ?? 'gray') . '</td><td class="r">' . num($r['reach']) . '</td><td class="r">' . num($r['clicks']) . '</td></tr>';
        $pt .= '</tbody></table>';
    } else $pt = '<div class="nochart">Aún no hay publicaciones planificadas para este evento.</div>';
    $h .= panel('Publicaciones de este evento', $pt . (can('marketing', 'w') ? '<p>' . btn('Planificar publicación', url('admin/marketing', ['tab' => 'calendario', 'do' => 'new', 'ev' => $id])) . '</p>' : ''));
    break;

case 'opiniones':
    $fb = rows('SELECT f.*, c.name FROM feedback f LEFT JOIN orders o ON o.id=f.order_id LEFT JOIN customers c ON c.id=o.customer_id WHERE f.event_id=? ORDER BY f.created_at DESC', [$id]);
    if (!$fb) { $h .= '<div class="empty"><b>Todavía no hay opiniones.</b><br>Cuando termine el evento, envía la encuesta desde la pestaña Resumen. Las respuestas aparecerán aquí.</div>'; break; }
    $n = count($fb); $avg = array_sum(array_column($fb, 'rating')) / $n;
    $pro = count(array_filter($fb, fn($f) => $f['nps'] >= 9)); $det = count(array_filter($fb, fn($f) => $f['nps'] <= 6)); $nps = round(100 * ($pro - $det) / $n);
    $h .= '<div class="kpis">' . kpi('Valoración media', num($avg, 1) . '<i> / 5</i>', stars($avg)) . kpi('Recomendación (NPS)', ($nps > 0 ? '+' : '') . $nps, $pro . ' la recomendarían seguro') . kpi('Respuestas', $n, 'de ' . (int)val("SELECT COUNT(*) FROM orders WHERE event_id=? AND status='paid'", [$id]) . ' reservas') . '</div>';
    $c = '';
    foreach ($fb as $f) if (trim((string)$f['liked']) !== '' || trim((string)$f['improve']) !== '')
        $c .= '<div class="quote">' . stars($f['rating']) . ' <b>' . e($f['name'] ?: 'Anónimo') . '</b>' . ($f['liked'] ? '<p>' . e($f['liked']) . '</p>' : '') . ($f['improve'] ? '<p class="mute">A mejorar: ' . e($f['improve']) . '</p>' : '') . '</div>';
    $h .= panel('Comentarios', $c ?: '<div class="nochart">Nadie ha dejado comentarios escritos.</div>');
    break;
}
if ($sub) { $h .= $sub['html']; $actions = $sub['mode'] === 'list' ? $sub['actions'] : ''; }
admin_page($ev['title'], tabs('admin/event', ['id' => $id], $tabsAll, $tab) . $h, $actions, url('admin/events'));
