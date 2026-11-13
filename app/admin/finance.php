<?php
// Finanzas: resumen, facturas de proveedores, pagos, facturas emitidas y liquidaciones a partners
need('finance');
$w = can('finance', 'w');
$tabsAll = ['resumen' => 'Resumen', 'facturas' => 'Facturas de proveedores', 'liquidaciones' => 'Liquidaciones a partners', 'pagos' => 'Pagos realizados', 'emitidas' => 'Facturas emitidas'];
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'resumen';
$P = ['tab' => $tab]; $self = fn($x = []) => url('admin/finance', $x + $P);
$payMethods = ['transfer' => 'Transferencia', 'bizum' => 'Bizum', 'cash' => 'Efectivo', 'card' => 'Tarjeta', 'domiciliado' => 'Domiciliación'];
$INV_STATUS = ['pending' => 'Pendiente', 'partial' => 'Pago parcial', 'paid' => 'Pagada']; $INV_BADGE = ['pending' => 'orange', 'partial' => 'blue', 'paid' => 'green'];

function settlement_calc(array $ev) {
    $rev = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE event_id=? AND status='paid'", [$ev['id']]);
    $gross = round($rev / (1 + (float)$ev['tax_pct'] / 100), 2);
    $pct = (float)$ev['partner_share_pct'] > 0 ? (float)$ev['partner_share_pct'] : (float)val('SELECT share_pct FROM partners WHERE id=?', [$ev['partner_id']]);
    return ['gross' => $gross, 'share_pct' => $pct, 'fee' => (float)$ev['partner_fee'], 'amount' => round($gross * $pct / 100 + (float)$ev['partner_fee'], 2)];
}

/* ---------- Acciones ---------- */
$act = $_GET['act'] ?? '';
if (is_post() && $act !== '') {
    need('finance', 'w');
    $amount = round((float)str_replace(',', '.', (string)($_POST['amount'] ?? 0)), 2);
    $pay = ['amount' => $amount, 'paid_at' => $_POST['paid_at'] ?? date('Y-m-d'), 'method' => isset($payMethods[$_POST['method'] ?? '']) ? $_POST['method'] : 'transfer', 'reference' => $_POST['reference'] ?? '', 'user_id' => user()['id'], 'created_at' => now()];
    if ($act === 'pay') {
        $inv = row('SELECT * FROM supplier_invoices WHERE id=?', [(int)($_GET['inv'] ?? 0)]);
        if ($inv && $amount > 0) {
            insert('payments', $pay + ['kind' => 'supplier', 'supplier_invoice_id' => $inv['id']]);
            $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE supplier_invoice_id=?', [$inv['id']]);
            update('supplier_invoices', ['paid' => $paid, 'status' => $paid >= (float)$inv['total'] - 0.005 ? 'paid' : 'partial'], $inv['id']);
            log_activity('Pago a proveedor', money($amount) . ' · factura ' . $inv['num']); flash('Pago de ' . money($amount) . ' registrado.');
        } else flash('Indica un importe mayor que cero.', 'err');
        redirect($self(['do' => 'edit', 'rid' => (int)($_GET['inv'] ?? 0)]));
    }
    if ($act === 'delpay') {
        $pm = row('SELECT * FROM payments WHERE id=?', [(int)($_GET['p'] ?? 0)]);
        if ($pm) {
            q('DELETE FROM payments WHERE id=?', [$pm['id']]);
            if ($pm['supplier_invoice_id']) { $inv = row('SELECT * FROM supplier_invoices WHERE id=?', [$pm['supplier_invoice_id']]); $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE supplier_invoice_id=?', [$pm['supplier_invoice_id']]); update('supplier_invoices', ['paid' => $paid, 'status' => $paid <= 0 ? 'pending' : ($paid >= (float)$inv['total'] - 0.005 ? 'paid' : 'partial')], $inv['id']); }
            if ($pm['settlement_id']) { $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE settlement_id=?', [$pm['settlement_id']]); $s = row('SELECT * FROM settlements WHERE id=?', [$pm['settlement_id']]); update('settlements', ['paid' => $paid, 'status' => $paid >= (float)$s['amount'] - 0.005 && $paid > 0 ? 'paid' : 'pending'], $s['id']); }
            log_activity('Pago anulado', money($pm['amount'])); flash('Pago anulado.');
        }
        redirect($_SERVER['HTTP_REFERER'] ?? $self());
    }
    if ($act === 'settle') {
        $ev = row('SELECT * FROM events WHERE id=? AND partner_id IS NOT NULL', [(int)($_GET['ev'] ?? 0)]);
        if ($ev && !val('SELECT COUNT(*) FROM settlements WHERE event_id=?', [$ev['id']])) {
            insert('settlements', settlement_calc($ev) + ['partner_id' => $ev['partner_id'], 'event_id' => $ev['id'], 'status' => 'pending', 'created_at' => now()]);
            log_activity('Liquidación generada', $ev['title']); flash('Liquidación generada. Revísala y registra el pago cuando lo hagas.');
        }
        redirect($self());
    }
    if ($act === 'paysettle') {
        $s = row('SELECT * FROM settlements WHERE id=?', [(int)($_GET['s'] ?? 0)]);
        if ($s && $amount > 0) {
            insert('payments', $pay + ['kind' => 'partner', 'settlement_id' => $s['id']]);
            $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE settlement_id=?', [$s['id']]);
            update('settlements', ['paid' => $paid, 'status' => $paid >= (float)$s['amount'] - 0.005 ? 'paid' : 'pending'], $s['id']);
            log_activity('Pago a partner', money($amount)); flash('Pago de ' . money($amount) . ' registrado.');
        }
        redirect($self());
    }
    if ($act === 'delsettle') {
        $s = row('SELECT * FROM settlements WHERE id=?', [(int)($_GET['s'] ?? 0)]);
        if ($s && (float)$s['paid'] <= 0) { q('DELETE FROM settlements WHERE id=?', [$s['id']]); flash('Liquidación eliminada. Puedes volver a generarla.'); }
        elseif ($s) flash('Esta liquidación ya tiene pagos. Anula antes los pagos desde "Pagos realizados".', 'err');
        redirect($self());
    }
}
$payForm = fn($action, $amount) => '<form method="post" action="' . e($action) . '" class="payform">' . csrf_field()
    . '<label class="fld"><span>Importe</span><input type="number" step="0.01" min="0.01" name="amount" value="' . e(number_format(max(0, $amount), 2, '.', '')) . '" required></label>'
    . '<label class="fld"><span>Fecha</span><input type="date" name="paid_at" value="' . date('Y-m-d') . '" required></label><label class="fld"><span>Medio</span><select name="method">' . implode('', array_map(fn($k, $l) => "<option value=\"$k\">$l</option>", array_keys($payMethods), $payMethods)) . '</select></label>'
    . '<label class="fld"><span>Referencia</span><input name="reference" placeholder="Opcional"></label><button class="btn primary">Registrar pago</button></form>';

$h = ''; $actions = ''; $back = '';
switch ($tab) {
case 'resumen':
    $m0 = date('Y-m-01 00:00:00');
    $in = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid' AND paid_at>=?", [$m0]);
    $out = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=?', [date('Y-m-01')]);
    $pendIn = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='pending'");
    $due = row("SELECT COALESCE(SUM(total-paid),0) AS t, COALESCE(SUM(CASE WHEN due_at<? THEN total-paid ELSE 0 END),0) AS late FROM supplier_invoices WHERE status<>'paid'", [date('Y-m-d')]);
    $setl = (float)val("SELECT COALESCE(SUM(amount-paid),0) FROM settlements WHERE status<>'paid'");
    $h .= '<div class="kpis">' . kpi('Cobrado en ' . MESES[(int)date('n')], money($in), money($pendIn) . ' pendiente de cobro') . kpi('Pagado en ' . MESES[(int)date('n')], money($out))
        . kpi('Por pagar a proveedores', money($due['t']), (float)$due['late'] > 0 ? '<span class="neg">' . money($due['late']) . ' vencido</span>' : 'Nada vencido') . kpi('Por liquidar a partners', money($setl)) . '</div>';
    $ins = []; $outs = [];
    for ($i = 5; $i >= 0; $i--) { $k = date('Y-m', strtotime(date('Y-m-01') . " -$i month")); $l = mb_substr(MESES[(int)substr($k, 5)], 0, 3); $ins[$l] = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid' AND SUBSTR(paid_at,1,7)=?", [$k]); $outs[$l] = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE SUBSTR(paid_at,1,7)=?', [$k]); }
    $h .= '<div class="cols">' . panel('Cobros por ventas, últimos 6 meses', chart_bars($ins, 'money')) . panel('Pagos a proveedores y partners, últimos 6 meses', chart_bars($outs, 'money', 'var(--orange)')) . '</div>';
    $t = '<table class="plain"><thead><tr><th>Evento</th><th>Fecha</th><th class="r">Ingresos sin IVA</th><th class="r">Costes</th><th class="r">Partner</th><th class="r">Resultado</th></tr></thead><tbody>';
    foreach (rows("SELECT * FROM events WHERE status<>'draft' ORDER BY starts_at DESC LIMIT 12") as $ev) {
        $c = settlement_calc($ev); $partner = $ev['partner_id'] ? $c['amount'] : 0;
        $real = (float)val('SELECT COALESCE(SUM(base),0) FROM supplier_invoices WHERE event_id=?', [$ev['id']]);
        $plan = (float)val('SELECT COALESCE(SUM(qty*unit_cost),0) FROM setup_items WHERE event_id=?', [$ev['id']]) + (float)val('SELECT COALESCE(SUM(cost),0) FROM staff_assignments WHERE event_id=?', [$ev['id']]) + (float)val('SELECT COALESCE(v.rental_cost,0) FROM events e LEFT JOIN venues v ON v.id=e.venue_id WHERE e.id=?', [$ev['id']]);
        $cost = $real > 0 ? $real : $plan; $res = $c['gross'] - $cost - $partner;
        $t .= '<tr><td><a href="' . e(url('admin/event', ['id' => $ev['id'], 'tab' => 'presupuesto'])) . '">' . e($ev['title']) . '</a></td><td>' . fdate($ev['starts_at']) . '</td><td class="r">' . money($c['gross']) . '</td><td class="r">' . money($cost) . ($real > 0 ? '' : ' <span class="mute">prev.</span>') . '</td><td class="r">' . money($partner) . '</td><td class="r"><b class="' . ($res >= 0 ? 'pos' : 'neg') . '">' . money($res) . '</b></td></tr>';
    }
    $h .= panel('Resultado por evento', $t . '</tbody></table><p class="mute small">Los costes marcados como "prev." son los previstos en montaje, personal y alquiler, porque aún no hay facturas registradas para ese evento.</p>');
    break;

case 'facturas':
    $sub = crud(['table' => 'supplier_invoices', 'route' => 'admin/finance', 'params' => $P, 'perm' => 'finance', 'singular' => 'factura de proveedor', 'new' => 'Registrar factura', 'order' => "CASE WHEN status='paid' THEN 1 ELSE 0 END, due_at IS NULL, due_at, id DESC", 'search' => ['num', 'concept'], 'filters' => ['status', 'supplier_id'],
        'empty' => 'Registra aquí las facturas que recibes de proveedores y personal, y controla qué está pagado y qué vence pronto.',
        'fields' => [
            'supplier_id' => ['label' => 'Proveedor', 'type' => 'select', 'req' => 1, 'options' => fn() => pairs('SELECT id, name FROM suppliers ORDER BY name'), 'list' => 1],
            'num' => ['label' => 'Número de factura', 'req' => 1, 'list' => 1, 'short' => 'Nº'],
            'concept' => ['label' => 'Concepto', 'full' => 1, 'list' => 1],
            'event_id' => ['label' => 'Evento al que corresponde', 'type' => 'select', 'options' => fn() => pairs('SELECT id, title FROM events ORDER BY starts_at DESC'), 'empty' => 'Gasto general, sin evento', 'default' => (int)($_GET['ev'] ?? 0) ?: '', 'list' => 1, 'short' => 'Evento'],
            'issued_at' => ['label' => 'Fecha de la factura', 'type' => 'date', 'req' => 1, 'default' => date('Y-m-d')],
            'due_at' => ['label' => 'Vencimiento', 'type' => 'date', 'list' => 1, 'short' => 'Vence'],
            'base' => ['label' => 'Base imponible', 'type' => 'money', 'req' => 1, 'section' => 'Importe'],
            'tax_pct' => ['label' => 'IVA', 'type' => 'pct', 'default' => 21],
            'withholding_pct' => ['label' => 'Retención IRPF', 'type' => 'pct', 'default' => 0, 'help' => 'Solo si la factura la lleva (autónomos).'],
            'total' => ['label' => 'Total a pagar', 'type' => 'static', 'render' => fn($v) => '<div class="total">Total a pagar: <b>' . money($v ?: 0) . '</b> <span class="mute">se calcula al guardar</span></div>', 'list' => 0],
            'status' => ['label' => 'Estado', 'type' => 'static', 'render' => fn() => '', 'options' => $INV_STATUS],
            'file' => ['label' => 'Factura en PDF o foto', 'type' => 'file', 'full' => 1],
            'notes' => ['label' => 'Notas', 'type' => 'textarea', 'rows' => 2],
        ],
        'cols' => ['Total' => fn($r) => '<span class="n">' . money($r['total']) . '</span>', 'Estado' => fn($r) => $r['status'] !== 'paid' && $r['due_at'] && $r['due_at'] < date('Y-m-d') ? badge('Vencida', 'red') : badge($INV_STATUS[$r['status']] ?? 'Pendiente', $INV_BADGE[$r['status']] ?? 'orange')],
        'validate' => function (array &$d, $rec) {
            $b = round((float)str_replace(',', '.', $d['base']), 2);
            $d['total'] = round($b * (1 + (float)$d['tax_pct'] / 100) - $b * (float)$d['withholding_pct'] / 100, 2);
            $paid = $rec ? (float)$rec['paid'] : 0;
            $d['status'] = $paid <= 0 ? 'pending' : ($paid >= $d['total'] - 0.005 ? 'paid' : 'partial');
            return null;
        },
        'before_delete' => function ($r) { if ((float)$r['paid'] > 0) throw new RuntimeException('Esta factura tiene pagos registrados. Anúlalos antes de eliminarla.'); },
        'form_extra' => function ($rec) use ($payForm, $self, $w, $payMethods) {
            $rest = (float)$rec['total'] - (float)$rec['paid']; $b = '';
            foreach (rows('SELECT * FROM payments WHERE supplier_invoice_id=? ORDER BY paid_at', [$rec['id']]) as $pm)
                $b .= '<div class="tk">' . fdate($pm['paid_at']) . ' · <b>' . money($pm['amount']) . '</b> · ' . e($payMethods[$pm['method']] ?? $pm['method']) . ($pm['reference'] ? ' · ' . e($pm['reference']) : '') . ($w ? ' ' . post_btn('Anular', $self(['act' => 'delpay', 'p' => $pm['id']]), 'sm link', '¿Anular este pago?') : '') . '</div>';
            $b .= $rest > 0.005 ? '<p>Queda por pagar <b>' . money($rest) . '</b> de ' . money($rec['total']) . '.</p>' . ($w ? $payForm($self(['act' => 'pay', 'inv' => $rec['id']]), $rest) : '') : '<p>' . badge('Factura pagada por completo', 'green') . '</p>';
            return panel('Pagos de esta factura', $b);
        },
    ]);
    $h .= $sub['html']; $actions = $sub['mode'] === 'list' ? $sub['actions'] : ''; $back = $sub['mode'] === 'form' ? $self() : '';
    break;

case 'liquidaciones':
    $h .= '<p class="intro">La liquidación es lo que corresponde al partner por cada evento: su porcentaje sobre la venta sin IVA más el importe fijo acordado.</p>';
    $todo = rows("SELECT e.* , p.name AS pname FROM events e JOIN partners p ON p.id=e.partner_id WHERE e.status<>'draft' AND e.starts_at<? AND NOT EXISTS (SELECT 1 FROM settlements s WHERE s.event_id=e.id) ORDER BY e.starts_at", [now()]);
    foreach ($todo as $ev) {
        $c = settlement_calc($ev);
        $h .= '<div class="card panel row"><div><b>' . e($ev['title']) . '</b> · ' . fdate($ev['starts_at']) . '<br><span class="mute">' . e($ev['pname']) . ': ' . num($c['share_pct'], 0) . ' % de ' . money($c['gross']) . ((float)$c['fee'] ? ' + ' . money($c['fee']) . ' fijos' : '') . ' = <b>' . money($c['amount']) . '</b></span></div>'
            . ($w ? post_btn('Generar liquidación', $self(['act' => 'settle', 'ev' => $ev['id']]), 'primary') : '') . '</div>';
    }
    $list = rows('SELECT s.*, p.name AS pname, p.iban, e.title, e.starts_at FROM settlements s JOIN partners p ON p.id=s.partner_id JOIN events e ON e.id=s.event_id ORDER BY s.id DESC');
    if (!$list && !$todo) { $h .= '<div class="empty"><b>No hay liquidaciones todavía.</b><br>Cuando termine un evento con partner, aparecerá aquí para generar su liquidación.</div>'; break; }
    foreach ($list as $s) {
        $rest = (float)$s['amount'] - (float)$s['paid'];
        $h .= '<div class="card panel"><div class="row"><div><b>' . e($s['pname']) . '</b> · ' . e($s['title']) . ' (' . fdate($s['starts_at']) . ')<br><span class="mute">' . num($s['share_pct'], 0) . ' % de ' . money($s['gross']) . ((float)$s['fee'] ? ' + ' . money($s['fee']) . ' fijos' : '') . ($s['iban'] ? ' · IBAN ' . e($s['iban']) : '') . '</span></div>'
            . '<div class="r"><b class="big">' . money($s['amount']) . '</b><br>' . ($s['status'] === 'paid' ? badge('Pagada', 'green') : badge($s['paid'] > 0 ? 'Pagado ' . money($s['paid']) : 'Pendiente de pago', 'orange')) . '</div></div>';
        if ($w && $rest > 0.005) $h .= '<details><summary>Registrar pago</summary>' . $payForm($self(['act' => 'paysettle', 's' => $s['id']]), $rest) . '</details>' . ((float)$s['paid'] <= 0 ? post_btn('Eliminar y recalcular', $self(['act' => 'delsettle', 's' => $s['id']]), 'sm link', '¿Eliminar esta liquidación? Podrás generarla de nuevo con las ventas actualizadas.') : '');
        $h .= '</div>';
    }
    break;

case 'pagos':
    $list = rows('SELECT pm.*, s.name AS sname, si.num AS inum, si.concept, p.name AS pname, e.title FROM payments pm
        LEFT JOIN supplier_invoices si ON si.id=pm.supplier_invoice_id LEFT JOIN suppliers s ON s.id=si.supplier_id
        LEFT JOIN settlements st ON st.id=pm.settlement_id LEFT JOIN partners p ON p.id=st.partner_id LEFT JOIN events e ON e.id=st.event_id ORDER BY pm.paid_at DESC, pm.id DESC LIMIT 500');
    if (($_GET['do'] ?? '') === 'csv') csv_out('pagos', ['Fecha', 'Tipo', 'Destinatario', 'Concepto', 'Importe', 'Medio', 'Referencia'],
        array_map(fn($r) => [fdate($r['paid_at']), $r['kind'] === 'partner' ? 'Partner' : 'Proveedor', $r['sname'] ?: $r['pname'], $r['kind'] === 'partner' ? 'Liquidación ' . $r['title'] : 'Factura ' . $r['inum'] . ' ' . $r['concept'], num($r['amount'], 2), $payMethods[$r['method']] ?? $r['method'], $r['reference']], $list));
    if (!$list) { $h .= '<div class="empty"><b>Todavía no hay pagos registrados.</b><br>Se registran desde cada factura de proveedor o liquidación.</div>'; break; }
    $actions = btn('Descargar', $self(['do' => 'csv']));
    $h .= '<div class="card table"><table><thead><tr><th>Fecha</th><th>Destinatario</th><th>Concepto</th><th>Medio</th><th class="r">Importe</th><th></th></tr></thead><tbody>';
    foreach ($list as $r) $h .= '<tr><td>' . fdate($r['paid_at']) . '</td><td><b>' . e($r['sname'] ?: $r['pname']) . '</b> ' . badge($r['kind'] === 'partner' ? 'Partner' : 'Proveedor') . '</td><td>' . e($r['kind'] === 'partner' ? 'Liquidación de ' . $r['title'] : 'Factura ' . $r['inum']) . '</td><td>' . e($payMethods[$r['method']] ?? $r['method']) . ($r['reference'] ? ' <span class="mute">' . e($r['reference']) . '</span>' : '') . '</td><td class="r"><span class="n">' . money($r['amount']) . '</span></td><td class="r">' . ($w ? post_btn('Anular', $self(['act' => 'delpay', 'p' => $r['id']]), 'sm link', '¿Anular este pago?') : '') . '</td></tr>';
    $h .= '</tbody></table></div>';
    break;

case 'emitidas':
    $list = rows('SELECT i.*, o.token, o.num AS onum FROM invoices_out i JOIN orders o ON o.id=i.order_id ORDER BY i.id DESC LIMIT 1000');
    if (($_GET['do'] ?? '') === 'csv') csv_out('facturas-emitidas', ['Número', 'Fecha', 'Cliente', 'NIF', 'Concepto', 'Base', 'IVA %', 'Cuota IVA', 'Total', 'Reserva'],
        array_map(fn($r) => [$r['num'], fdate($r['issued_at']), $r['customer_name'], $r['tax_id'], $r['concept'], num($r['base'], 2), num($r['tax_pct'], 2), num($r['tax'], 2), num($r['total'], 2), $r['onum']], $list));
    if (!$list) { $h .= '<div class="empty"><b>Todavía no se ha emitido ninguna factura.</b><br>Se generan solas al confirmar el pago de cada reserva.</div>'; break; }
    $qm = (int)ceil(date('n') / 3); $q0 = date('Y') . '-' . str_pad((string)(($qm - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
    $qt = row('SELECT COALESCE(SUM(base),0) AS b, COALESCE(SUM(tax),0) AS t, COUNT(*) AS n FROM invoices_out WHERE issued_at>=?', [$q0]);
    $h .= '<div class="kpis">' . kpi('Base facturada este trimestre', money($qt['b'])) . kpi('IVA repercutido este trimestre', money($qt['t'])) . kpi('Facturas del trimestre', (int)$qt['n']) . '</div>';
    $actions = btn('Descargar', $self(['do' => 'csv']));
    $h .= '<div class="card table"><table><thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Concepto</th><th class="r">Base</th><th class="r">IVA</th><th class="r">Total</th></tr></thead><tbody>';
    foreach ($list as $r) { $l = url('pedido/' . $r['token'] . '/factura', ['n' => $r['id']]);
        $h .= '<tr><td><a class="lead" href="' . e($l) . '" target="_blank">' . e($r['num']) . '</a></td><td>' . fdate($r['issued_at']) . '</td><td>' . e($r['customer_name']) . '</td><td>' . e($r['concept']) . '</td><td class="r">' . money($r['base']) . '</td><td class="r">' . money($r['tax']) . '</td><td class="r"><span class="n">' . money($r['total']) . '</span></td></tr>'; }
    $h .= '</tbody></table></div>';
    break;
}
admin_page('Finanzas', ($back ? '' : tabs('admin/finance', [], $tabsAll, $tab)) . $h, $actions, $back);
