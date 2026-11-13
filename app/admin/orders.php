<?php
// Ventas: listado de reservas, ficha de reserva y venta manual
need('orders');
expire_orders();
$w = can('orders', 'w');
$id = (int)($_GET['id'] ?? 0); $do = $_GET['do'] ?? '';
$manualMethods = ['cash' => 'Efectivo', 'tpv' => 'Datáfono', 'transfer' => 'Transferencia', 'bizum' => 'Bizum', 'invite' => 'Invitación (sin coste)'];

/* ---------- Ficha de una reserva ---------- */
if ($id) {
    $o = order_ctx($id);
    if (!$o) redirect(url('admin/orders'));
    if (is_post()) {
        need('orders', 'w');
        $act = $_GET['act'] ?? '';
        if ($act === 'pay') { if (mark_paid($id, trim((string)($_POST['ref'] ?? '')))) { log_activity('Pago confirmado', $o['num']); flash('Pago confirmado. Las entradas se han enviado por correo.'); } }
        elseif ($act === 'cancel' && $o['status'] === 'pending') { cancel_order($id); log_activity('Reserva cancelada', $o['num']); flash('Reserva cancelada. Las plazas vuelven a estar disponibles.'); }
        elseif ($act === 'refund' && $o['status'] === 'paid') {
            $msg = 'Reserva devuelta: entradas anuladas y factura rectificativa emitida.';
            if ($o['method'] === 'stripe' && $o['stripe_pi'] && stripe_on()) {
                try { stripe_api('POST', 'refunds', ['payment_intent' => $o['stripe_pi']]); $msg .= ' El importe se ha devuelto a la tarjeta.'; }
                catch (Throwable $e) { flash('No se pudo devolver el cobro en la pasarela: ' . $e->getMessage() . ' La reserva no se ha modificado.', 'err'); redirect(url('admin/orders', ['id' => $id])); }
            } else $msg .= ' Recuerda devolver el dinero a la clienta por el mismo medio en que pagó.';
            cancel_order($id); log_activity('Reserva devuelta', $o['num']); flash($msg);
        } elseif ($act === 'resend') { $o['status'] === 'paid' ? mail_order_paid($id) : mail_order_pending($id); flash('Correo reenviado a ' . $o['cemail'] . '.'); }
        elseif ($act === 'notes') { update('orders', ['notes' => (string)($_POST['notes'] ?? '')], $id); flash('Notas guardadas.'); }
        redirect(url('admin/orders', ['id' => $id]));
    }
    $items = rows('SELECT * FROM order_items WHERE order_id=?', [$id]);
    $tix = rows('SELECT * FROM tickets WHERE order_id=?', [$id]);
    $invs = rows('SELECT * FROM invoices_out WHERE order_id=? ORDER BY id', [$id]);
    $it = '<table class="plain"><tbody>';
    foreach ($items as $i) $it .= '<tr><td>' . (int)$i['qty'] . ' × ' . e($i['name']) . '</td><td class="r">' . money($i['qty'] * $i['unit_price']) . '</td></tr>';
    if ((float)$o['discount'] > 0) $it .= '<tr><td>Descuento' . ($o['discount_code'] ? ' <span class="mute">' . e($o['discount_code']) . '</span>' : '') . '</td><td class="r">−' . money($o['discount']) . '</td></tr>';
    $it .= '</tbody><tfoot><tr><td><b>Total</b></td><td class="r"><b>' . money($o['total']) . '</b></td></tr></tfoot></table>';
    $info = '<dl class="dl"><dt>Estado</dt><dd>' . badge(ORDER_STATUS[$o['status']], ORDER_BADGE[$o['status']]) . '</dd><dt>Evento</dt><dd><a href="' . e(url('admin/event', ['id' => $o['event_id']])) . '">' . e($o['title']) . '</a> · ' . fdt($o['starts_at']) . '</dd>'
        . '<dt>Clienta</dt><dd><a href="' . e(url('admin/customers', ['do' => 'edit', 'rid' => $o['customer_id']])) . '">' . e($o['cname']) . '</a><br><span class="mute">' . e($o['cemail']) . '</span></dd>'
        . '<dt>Forma de pago</dt><dd>' . e(PAY_METHODS[$o['method']] ?? $o['method']) . ($o['pay_ref'] ? ' <span class="mute">' . e($o['pay_ref']) . '</span>' : '') . '</dd>'
        . '<dt>Reservado</dt><dd>' . fdt($o['created_at']) . ($o['paid_at'] ? ' · pagado el ' . fdt($o['paid_at']) : '') . '</dd>'
        . '<dt>Origen</dt><dd>' . e($o['created_by'] ? 'Venta manual' : channel_label($o['utm_source'])) . ($o['utm_campaign'] ? ' <span class="mute">' . e($o['utm_campaign']) . '</span>' : '') . '</dd>'
        . '<dt>Página de la clienta</dt><dd><a href="' . e(url('pedido/' . $o['token'])) . '" target="_blank">Ver lo que ve ella</a></dd></dl>';
    $ac = '';
    if ($w) {
        if ($o['status'] === 'pending') $ac .= '<form method="post" action="' . e(url('admin/orders', ['id' => $id, 'act' => 'pay'])) . '" class="payform">' . csrf_field() . '<label class="fld"><span>Referencia del cobro (opcional)</span><input name="ref" placeholder="Nº de transferencia, Bizum de las 12:40…"></label><button class="btn primary">Confirmar pago recibido</button></form>';
        $ac .= '<div class="btnrow">';
        if ($o['status'] !== 'cancelled' && $o['status'] !== 'refunded') $ac .= post_btn($o['status'] === 'paid' ? 'Reenviar entradas por correo' : 'Reenviar instrucciones de pago', url('admin/orders', ['id' => $id, 'act' => 'resend']));
        if ($o['status'] === 'pending') $ac .= post_btn('Cancelar reserva', url('admin/orders', ['id' => $id, 'act' => 'cancel']), 'danger', '¿Cancelar esta reserva y liberar sus plazas?');
        if ($o['status'] === 'paid') $ac .= post_btn('Devolver reserva', url('admin/orders', ['id' => $id, 'act' => 'refund']), 'danger', '¿Devolver esta reserva? Se anularán las entradas y se emitirá una factura rectificativa.');
        if ($o['status'] === 'cancelled') $ac .= post_btn('Recuperar como pagada', url('admin/orders', ['id' => $id, 'act' => 'pay']), '', '¿Recuperar esta reserva y marcarla como pagada? Comprueba antes que quedan plazas.');
        $ac .= '</div>';
    }
    $tk = '';
    foreach ($tix as $t) $tk .= '<div class="tk"><b>' . e($t['type_name']) . '</b> <code>' . e($t['code']) . '</code> ' . ($t['status'] === 'used' ? badge('Ha entrado ' . fdt($t['checked_in_at']), 'green') : ($t['status'] === 'void' ? badge('Anulada', 'red') : badge('Válida', 'blue'))) . '</div>';
    $iv = '';
    foreach ($invs as $i) $iv .= '<div class="tk"><a href="' . e(url('pedido/' . $o['token'] . '/factura', ['n' => $i['id']])) . '" target="_blank">' . e($i['num']) . '</a> ' . fdate($i['issued_at']) . ' · ' . money($i['total']) . ($i['serie'] === 'R' ? ' ' . badge('Rectificativa', 'red') : '') . '</div>';
    $notes = $w ? '<form method="post" action="' . e(url('admin/orders', ['id' => $id, 'act' => 'notes'])) . '">' . csrf_field() . '<textarea name="notes" rows="3" placeholder="Alergias, mesa preferida, acompañantes…">' . e($o['notes']) . '</textarea><p><button class="btn sm">Guardar notas</button></p></form>' : '<p>' . nl2br(e($o['notes'])) . '</p>';
    admin_page('Reserva ' . $o['num'], '<div class="cols">' . panel('Datos de la reserva', $info . $ac) . '<div>' . panel('Importe', $it) . panel('Entradas', $tk ?: '<div class="nochart">Las entradas se emiten al confirmar el pago.</div>')
        . ($iv ? panel('Facturas', $iv) : '') . panel('Notas internas', $notes) . '</div></div>', '', url('admin/orders'));
    return;
}

/* ---------- Venta manual ---------- */
if ($do === 'new') {
    need('orders', 'w');
    $evs = rows("SELECT id, title, starts_at FROM events WHERE status IN ('draft','published','closed') ORDER BY starts_at");
    $evId = (int)($_GET['ev'] ?? ($_POST['ev'] ?? 0)); $ev = $evId ? row('SELECT * FROM events WHERE id=?', [$evId]) : null; $err = '';
    if (is_post() && $ev) {
        try {
            $m = (string)($_POST['method'] ?? 'cash'); if (!isset($manualMethods[$m])) $m = 'cash';
            $o = create_order($ev, (array)($_POST['qty'] ?? []), ['name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? ''], $m, '',
                ['admin' => true, 'paid' => !empty($_POST['paid']) || $m === 'invite', 'notes' => (string)($_POST['notes'] ?? ''), 'pay_ref' => 'Venta manual']);
            log_activity('Venta manual', $o['num']); flash('Reserva ' . $o['num'] . ' creada.');
            redirect(url('admin/orders', ['id' => $o['id']]));
        } catch (RuntimeException $e) { $err = $e->getMessage(); }
    }
    $h = get_form('admin/orders', ['do' => 'new'], 'toolbar') . '<select name="ev" data-auto><option value="">Elige el evento…</option>';
    foreach ($evs as $e1) $h .= '<option value="' . $e1['id'] . '"' . ($e1['id'] == $evId ? ' selected' : '') . '>' . e($e1['title'] . ' · ' . fdate($e1['starts_at'])) . '</option>';
    $h .= '</select></form>';
    if ($ev) {
        $h .= ($err ? '<div class="flash err">' . e($err) . '</div>' : '') . '<form method="post" class="card form" action="' . e(url('admin/orders', ['do' => 'new', 'ev' => $evId])) . '">' . csrf_field() . '<h2>Venta manual o invitación</h2><div class="grid"><div class="fld full"><span>Entradas</span><div class="qtys">';
        foreach (rows('SELECT * FROM ticket_types WHERE event_id=? ORDER BY sort, id', [$evId]) as $tt) {
            $left = tt_left($tt, $ev);
            $h .= '<label><input type="number" min="0" max="50" name="qty[' . $tt['id'] . ']" value="' . (int)($_POST['qty'][$tt['id']] ?? 0) . '"><span><b>' . e($tt['name']) . '</b> ' . money($tt['price']) . ' <em class="mute">' . ($left === null ? 'sin límite' : 'quedan ' . $left) . '</em></span></label>';
        }
        $h .= '</div></div>'
            . render_field('name', ['label' => 'Nombre y apellidos', 'req' => 1], $_POST['name'] ?? '') . render_field('email', ['label' => 'Correo', 'type' => 'email', 'req' => 1, 'help' => 'Recibirá aquí sus entradas.'], $_POST['email'] ?? '')
            . render_field('phone', ['label' => 'Teléfono', 'type' => 'tel'], $_POST['phone'] ?? '') . render_field('method', ['label' => 'Forma de pago', 'type' => 'select', 'req' => 1, 'options' => $manualMethods], $_POST['method'] ?? 'cash')
            . render_field('paid', ['label' => 'Ya está cobrado (emitir entradas ahora)', 'type' => 'check', 'full' => 1], is_post() ? !empty($_POST['paid']) : 1)
            . render_field('notes', ['label' => 'Notas internas', 'type' => 'textarea', 'rows' => 2], $_POST['notes'] ?? '')
            . '</div><div class="form-foot"><button class="btn primary">Crear reserva</button>' . btn('Cancelar', url('admin/orders')) . '</div></form>';
    }
    admin_page('Nueva venta', $h, '', url('admin/orders'));
    return;
}

/* ---------- Listado ---------- */
$where = '1=1'; $p = [];
$fe = (int)($_GET['ev'] ?? 0); $fs = (string)($_GET['st'] ?? ''); $s = trim((string)($_GET['s'] ?? ''));
if ($fe) { $where .= ' AND o.event_id=?'; $p[] = $fe; }
if (isset(ORDER_STATUS[$fs])) { $where .= ' AND o.status=?'; $p[] = $fs; }
if ($s !== '') { $where .= ' AND (o.num LIKE ? OR c.name LIKE ? OR c.email LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
$sql = "SELECT o.*, c.name AS cname, c.email AS cemail, e.title FROM orders o JOIN customers c ON c.id=o.customer_id JOIN events e ON e.id=o.event_id WHERE $where ORDER BY o.id DESC";
if ($do === 'csv') {
    csv_out('reservas', ['Reserva', 'Fecha', 'Evento', 'Clienta', 'Correo', 'Estado', 'Forma de pago', 'Subtotal', 'Descuento', 'Total', 'Código', 'Canal', 'Campaña'],
        array_map(fn($r) => [$r['num'], fdt($r['created_at']), $r['title'], $r['cname'], $r['cemail'], ORDER_STATUS[$r['status']], PAY_METHODS[$r['method']] ?? '', num($r['subtotal'], 2), num($r['discount'], 2), num($r['total'], 2), $r['discount_code'], channel_label($r['utm_source']), $r['utm_campaign']], rows($sql, $p)));
}
$per = 50; $pg = max(1, (int)($_GET['pg'] ?? 1));
$data = rows($sql . ' LIMIT ' . ($per + 1) . ' OFFSET ' . (($pg - 1) * $per), $p); $more = count($data) > $per; $data = array_slice($data, 0, $per);
$h = get_form('admin/orders', [], 'toolbar') . '<input type="search" name="s" value="' . e($s) . '" placeholder="Buscar por nombre, correo o nº de reserva…"><select name="ev" data-auto><option value="">Evento: todos</option>';
foreach (pairs('SELECT id, title FROM events ORDER BY starts_at DESC') as $k => $l) $h .= '<option value="' . $k . '"' . ($k == $fe ? ' selected' : '') . '>' . e($l) . '</option>';
$h .= '</select><select name="st" data-auto><option value="">Estado: todos</option>';
foreach (ORDER_STATUS as $k => $l) $h .= '<option value="' . $k . '"' . ($k === $fs ? ' selected' : '') . '>' . e($l) . '</option>';
$h .= '</select><button class="btn">Buscar</button></form>';
if (!$data) $h .= '<div class="empty"><b>' . ($s !== '' || $fe || $fs ? 'No hay reservas con ese filtro.' : 'Todavía no hay reservas.') . '</b><br>Las ventas de la web aparecerán aquí automáticamente.</div>';
else {
    $h .= '<div class="card table"><table><thead><tr><th>Reserva</th><th>Clienta</th><th>Evento</th><th>Fecha</th><th>Pago</th><th>Estado</th><th class="r">Total</th></tr></thead><tbody>';
    foreach ($data as $r) {
        $l = url('admin/orders', ['id' => $r['id']]);
        $h .= '<tr data-href="' . e($l) . '"><td><a class="lead" href="' . e($l) . '">' . e($r['num']) . '</a></td><td>' . e($r['cname']) . '</td><td>' . e($r['title']) . '</td><td>' . fdt($r['created_at']) . '</td><td>' . e(PAY_METHODS[$r['method']] ?? '') . '</td><td>' . badge(ORDER_STATUS[$r['status']], ORDER_BADGE[$r['status']]) . '</td><td class="r"><span class="n">' . money($r['total']) . '</span></td></tr>';
    }
    $h .= '</tbody></table></div>';
    $qp = ['s' => $s, 'ev' => $fe ?: null, 'st' => $fs];
    if ($pg > 1 || $more) $h .= '<div class="pager">' . ($pg > 1 ? btn('‹ Anteriores', url('admin/orders', $qp + ['pg' => $pg - 1])) : '') . ($more ? btn('Siguientes ›', url('admin/orders', $qp + ['pg' => $pg + 1])) : '') . '</div>';
}
admin_page('Ventas', $h, btn('Descargar', url('admin/orders', ['do' => 'csv', 's' => $s, 'ev' => $fe ?: null, 'st' => $fs])) . ($w ? btn('Nueva venta', url('admin/orders', ['do' => 'new']), 'primary') : ''));
