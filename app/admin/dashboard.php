<?php
need('dashboard');
expire_orders();
$u = user(); $isPartner = $u['role'] === 'partner'; $h = '';
$m0 = date('Y-m-01 00:00:00');

if (can('orders') || can('finance')) {
    $rev = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid' AND paid_at>=?", [$m0]);
    $tix = (int)val("SELECT COALESCE(SUM(oi.qty*oi.seats),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status='paid' AND o.paid_at>=?", [$m0]);
    $pend = row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE status='pending'");
    $upc = (int)val("SELECT COUNT(*) FROM events WHERE status IN ('published','closed') AND starts_at>=?", [now()]);
    $h .= '<div class="kpis">' . kpi('Ventas de ' . MESES[(int)date('n')], money($rev)) . kpi('Entradas vendidas este mes', num($tix))
        . kpi('Pendiente de cobro', money($pend['t']), (int)$pend['n'] . ((int)$pend['n'] === 1 ? ' reserva por confirmar' : ' reservas por confirmar')) . kpi('Próximos eventos', num($upc), 'publicados') . '</div>';
}

// Proximos eventos
$w = "status IN ('draft','published','closed') AND starts_at>=?"; $p = [date('Y-m-d 00:00:00')];
if ($isPartner) { $w .= ' AND partner_id=?'; $p[] = (int)$u['partner_id']; }
$evs = rows("SELECT * FROM events WHERE $w ORDER BY starts_at LIMIT 6", $p);
$b = '';
foreach ($evs as $ev) {
    $s = event_stats($ev['id']); $cap = (int)$ev['capacity']; $pct = $cap ? 100 * $s['sold'] / $cap : 0;
    $b .= '<a class="evrow" href="' . e(url('admin/event', ['id' => $ev['id']])) . '"><div class="when"><b>' . date('j', strtotime($ev['starts_at'])) . '</b><span>' . mb_substr(MESES[(int)date('n', strtotime($ev['starts_at']))], 0, 3) . '</span></div>'
        . '<div class="what"><b>' . e($ev['title']) . '</b> ' . badge(EVENT_STATUS[$ev['status']], EVENT_BADGE[$ev['status']]) . '<span>' . $s['sold'] . ($cap ? ' de ' . $cap : '') . ' entradas vendidas' . ($s['reserved'] ? ' · ' . $s['reserved'] . ' reservadas' : '') . '</span>' . ($cap ? progress($pct, $pct >= 90 ? 'green' : '') : '') . '</div></a>';
}
$h .= '<div class="cols">' . panel('Próximos eventos', $b ?: '<div class="nochart">No hay eventos próximos.' . (can('events', 'w') ? ' <a href="' . e(url('admin/events', ['do' => 'new'])) . '">Crear el primero</a>' : '') . '</div>');

// Avisos y tareas
$al = '';
if ($u['role'] === 'admin' && !public_methods()) $al .= '<a class="alert" href="' . e(url('admin/settings', ['tab' => 'pagos'])) . '"><i class="dot red"></i><span><b>Configura cómo vas a cobrar</b> Añade tu IBAN, tu Bizum o Stripe para poder vender en la web</span></a>';
if (can('orders', 'w')) {
    foreach (rows("SELECT o.id, o.num, o.total, o.method, c.name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.status='pending' AND o.method<>'stripe' ORDER BY o.created_at LIMIT 5") as $o)
        $al .= '<a class="alert" href="' . e(url('admin/orders', ['id' => $o['id']])) . '"><i class="dot orange"></i><span><b>Confirmar pago de ' . e($o['name']) . '</b> ' . money($o['total']) . ' por ' . e(mb_strtolower(PAY_METHODS[$o['method']] ?? '')) . ' · ' . e($o['num']) . '</span></a>';
}
if (can('finance')) {
    foreach (rows("SELECT si.id, si.num, si.total, si.paid, si.due_at, s.name FROM supplier_invoices si LEFT JOIN suppliers s ON s.id=si.supplier_id WHERE si.status<>'paid' AND si.due_at IS NOT NULL AND si.due_at<=? ORDER BY si.due_at LIMIT 5", [date('Y-m-d', time() + 7 * 86400)]) as $i)
        $al .= '<a class="alert" href="' . e(url('admin/finance', ['tab' => 'facturas', 'do' => 'edit', 'rid' => $i['id']])) . '"><i class="dot ' . ($i['due_at'] < date('Y-m-d') ? 'red' : 'orange') . '"></i><span><b>Factura de ' . e($i['name'] ?: 'proveedor') . '</b> ' . money((float)$i['total'] - (float)$i['paid']) . ' · ' . ($i['due_at'] < date('Y-m-d') ? 'vencida el ' : 'vence el ') . fdate($i['due_at']) . '</span></a>';
}
if (can('events') && !$isPartner) {
    $tw = can('events', 'w') && $u['role'] !== 'admin' && $u['role'] !== 'direccion' ? ' AND (t.assignee_id=' . (int)$u['id'] . ' OR t.assignee_id IS NULL)' : '';
    foreach (rows("SELECT t.*, e.title AS etitle FROM tasks t JOIN events e ON e.id=t.event_id WHERE t.done=0 AND t.due_date IS NOT NULL AND t.due_date<=? AND e.status IN ('draft','published','closed')$tw ORDER BY t.due_date LIMIT 8", [date('Y-m-d', time() + 7 * 86400)]) as $t)
        $al .= '<a class="alert" href="' . e(url('admin/event', ['id' => $t['event_id'], 'tab' => 'tareas'])) . '"><i class="dot ' . ($t['due_date'] < date('Y-m-d') ? 'red' : 'blue') . '"></i><span><b>' . e($t['title']) . '</b> ' . e($t['etitle']) . ' · ' . ($t['due_date'] < date('Y-m-d') ? 'atrasada desde el ' : 'para el ') . fdate($t['due_date'], 'd/m') . '</span></a>';
    foreach (rows("SELECT s.event_id, e.title, COUNT(*) AS n FROM setup_items s JOIN events e ON e.id=s.event_id WHERE s.status IN ('pendiente','pedido') AND e.status IN ('published','closed') AND e.starts_at>=? AND e.starts_at<=? GROUP BY s.event_id, e.title", [now(), date('Y-m-d H:i:s', time() + 10 * 86400)]) as $s)
        $al .= '<a class="alert" href="' . e(url('admin/event', ['id' => $s['event_id'], 'tab' => 'montaje'])) . '"><i class="dot orange"></i><span><b>Montaje sin confirmar</b> ' . (int)$s['n'] . ' elementos de ' . e($s['title']) . '</span></a>';
}
if (!$isPartner) $h .= panel('Pendiente de atender', $al ?: '<div class="nochart">Todo al día. No hay nada urgente.</div>');
$h .= '</div>';

if (can('orders') && !$isPartner) {
    $days = [];
    for ($i = 29; $i >= 0; $i--) $days[date('d/m', time() - $i * 86400)] = 0;
    foreach (rows("SELECT SUBSTR(paid_at,1,10) AS d, SUM(total) AS t FROM orders WHERE status='paid' AND paid_at>=? GROUP BY SUBSTR(paid_at,1,10)", [date('Y-m-d 00:00:00', time() - 29 * 86400)]) as $r) $days[fdate($r['d'], 'd/m')] = (float)$r['t'];
    $h .= panel('Ventas de los últimos 30 días', chart_bars($days, 'money'));
}
admin_page('Hola, ' . first_name($u['name']), $h, can('events', 'w') ? btn('Nuevo evento', url('admin/events', ['do' => 'new']), 'primary') : '');
