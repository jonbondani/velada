<?php
// Pagina privada de cada reserva: instrucciones de pago, entradas con QR, calendario y factura
$o = row('SELECT * FROM orders WHERE token=?', [$seg[1] ?? '']);
if (!$o) not_found();
$sub = $seg[2] ?? '';
if ($o['status'] === 'pending' && $o['stripe_session']) { stripe_sync($o); $o = row('SELECT * FROM orders WHERE id=?', [$o['id']]); }
$c = order_ctx($o['id']); $self = url('pedido/' . $o['token']);

if ($sub === 'ics') {
    $ev = row('SELECT * FROM events WHERE id=?', [$o['event_id']]);
    $u = fn($s) => gmdate('Ymd\THis\Z', strtotime($s)); $esc = fn($s) => addcslashes(str_replace(["\r", "\n"], ['', '\n'], (string)$s), ',;');
    $end = $ev['ends_at'] ?: date('Y-m-d H:i:s', strtotime($ev['starts_at']) + 3 * 3600);
    header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $ev['slug'] . '.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . $esc(brand()) . "//ES\r\nBEGIN:VEVENT\r\nUID:" . $o['token'] . "@velada\r\nDTSTAMP:" . $u(now()) . "\r\nDTSTART:" . $u($ev['starts_at']) . "\r\nDTEND:" . $u($end)
        . "\r\nSUMMARY:" . $esc($ev['title']) . "\r\nLOCATION:" . $esc(trim($c['vname'] . ', ' . $c['vaddress'] . ', ' . $c['vcity'], ', ')) . "\r\nDESCRIPTION:" . $esc('Tus entradas: ' . abs_url('pedido/' . $o['token'])) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}
if ($sub === 'pagar' && $o['status'] === 'pending' && stripe_on()) {
    try { q("UPDATE orders SET method='stripe' WHERE id=?", [$o['id']]); redirect(stripe_checkout_url($o)); }
    catch (RuntimeException $e) { flash('No hemos podido iniciar el pago con tarjeta. Inténtalo de nuevo en unos minutos.', 'err'); redirect($self); }
}
if ($sub === 'factura') {
    $w = 'order_id=?'; $p = [$o['id']]; if (!empty($_GET['n'])) { $w .= ' AND id=?'; $p[] = (int)$_GET['n']; }
    $i = row("SELECT * FROM invoices_out WHERE $w ORDER BY id", $p);
    if (!$i) not_found();
    $ref = $i['ref_invoice_id'] ? val('SELECT num FROM invoices_out WHERE id=?', [$i['ref_invoice_id']]) : '';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Factura ' . e($i['num']) . '</title><link rel="stylesheet" href="' . asset('site.css') . '"></head><body class="invoice"><div class="sheet">'
        . '<header><div><div class="logo">' . e(brand()) . '</div><p>' . e(setting('legal_name', brand())) . (setting('tax_id') ? '<br>NIF ' . e(setting('tax_id')) : '') . (setting('address') ? '<br>' . e(setting('address')) : '') . '</p></div>'
        . '<div class="r"><h1>' . ($i['serie'] === 'R' ? 'Factura rectificativa' : ($i['tax_id'] ? 'Factura' : 'Factura simplificada')) . '</h1><p><b>' . e($i['num']) . '</b><br>' . fdate($i['issued_at']) . ($ref ? '<br>Rectifica a ' . e($ref) : '') . '</p></div></header>'
        . '<section><span>Cliente</span><p><b>' . e($i['customer_name']) . '</b>' . ($i['tax_id'] ? '<br>NIF ' . e($i['tax_id']) : '') . ($i['address'] ? '<br>' . e($i['address']) : '') . '</p></section>'
        . '<table><thead><tr><th>Concepto</th><th class="r">Importe</th></tr></thead><tbody><tr><td>' . e($i['concept']) . '<br><small>Reserva ' . e($o['num']) . '</small></td><td class="r">' . money($i['base']) . '</td></tr></tbody>'
        . '<tfoot><tr><td class="r">Base imponible</td><td class="r">' . money($i['base']) . '</td></tr><tr><td class="r">IVA ' . num($i['tax_pct'], (float)$i['tax_pct'] == (int)$i['tax_pct'] ? 0 : 2) . ' %</td><td class="r">' . money($i['tax']) . '</td></tr><tr class="tot"><td class="r">Total</td><td class="r">' . money($i['total']) . '</td></tr></tfoot></table>'
        . '<p class="noprint"><button class="cta" onclick="print()">Imprimir o guardar en PDF</button></p></div></body></html>';
    exit;
}

$first = e(first_name($c['cname'])); $past = $c['starts_at'] < now();
$where = '<div class="facts card"><div><span>Cuándo</span><b>' . e(ucfirst(fecha_larga($c['starts_at'], true))) . '</b></div>' . ($c['vname'] ? '<div><span>Dónde</span><b>' . e($c['vname']) . '</b><em>' . e(trim($c['vaddress'] . ', ' . $c['vcity'], ', ')) . '</em></div>' : '') . '</div>';
$h = '<section class="wrap narrow order"><p class="eyebrow">Reserva ' . e($o['num']) . '</p>';
if ($o['status'] === 'paid') {
    $tix = rows("SELECT * FROM tickets WHERE order_id=? AND status<>'void'", [$o['id']]);
    $h .= '<h1>' . ($past ? 'Gracias por venir, ' . $first : 'Nos vemos pronto, ' . $first) . '</h1><p class="lead">Tu reserva para <b>' . e($c['title']) . '</b> está confirmada.' . ($past ? '' : ' Enseña estas entradas en el móvil al llegar.') . '</p>' . $where;
    if ($past && !val('SELECT COUNT(*) FROM feedback WHERE order_id=?', [$o['id']])) $h .= '<p><a class="cta" href="' . e(url('opinion/' . $o['token'])) . '">Cuéntanos qué te pareció</a></p>';
    $h .= '<div class="tickets">';
    foreach ($tix as $n => $t) $h .= '<div class="ticket' . ($t['status'] === 'used' ? ' used' : '') . '"><div class="qr" data-qr="' . e(abs_url('c/' . $t['code'])) . '"></div><div class="tinfo"><span>Entrada ' . ($n + 1) . ' de ' . count($tix) . '</span><b>' . e($t['type_name']) . '</b><em>' . e($t['holder_name']) . '</em><code>' . e($t['code']) . '</code>' . ($t['status'] === 'used' ? '<i>Ya utilizada</i>' : '') . '</div></div>';
    $h .= '</div><p class="actions noprint">' . ($past ? '' : '<a class="ghost" href="' . e($self . '/ics') . '">Añadir al calendario</a>') . '<button class="ghost" onclick="print()">Imprimir entradas</button>' . ((float)$o['total'] > 0 ? '<a class="ghost" href="' . e($self . '/factura') . '" target="_blank">Ver factura</a>' : '') . '</p>'
        . '<p class="small">Hemos enviado este mismo enlace a ' . e($c['cemail']) . '. Guárdalo: es tu acceso a las entradas.</p>';
} elseif ($o['status'] === 'pending') {
    $until = strtotime($o['created_at']) + max(1, (int)setting('hold_hours', 72)) * 3600;
    $h .= '<h1>Ya casi está, ' . $first . '</h1><p class="lead">Hemos guardado tus plazas para <b>' . e($c['title']) . '</b>. Solo falta el pago de <b>' . money($o['total']) . '</b>.</p>';
    if ($o['method'] === 'stripe') $h .= '<div class="card paybox"><p>El pago con tarjeta no se ha completado.</p><p><a class="cta" href="' . e($self . '/pagar') . '">Pagar ahora con tarjeta</a></p></div>';
    else $h .= '<div class="card paybox">' . pay_instructions($o) . '<p class="small">Guardamos tu reserva hasta el ' . date('j', $until) . ' de ' . MESES[(int)date('n', $until)] . ' a las ' . date('H:i', $until) . ' h. En cuanto confirmemos el pago recibirás tus entradas por correo y aparecerán en esta misma página.</p>'
        . (stripe_on() ? '<p><a class="ghost" href="' . e($self . '/pagar') . '">Prefiero pagar ahora con tarjeta</a></p>' : '') . '</div>';
    $h .= $where;
} else {
    $h .= '<h1>' . ($o['status'] === 'refunded' ? 'Reserva devuelta' : 'Reserva cancelada') . '</h1><p class="lead">' . ($o['status'] === 'refunded' ? 'Esta reserva se ha devuelto y sus entradas ya no son válidas.' : 'Esta reserva se canceló porque no llegó el pago a tiempo o a petición tuya.') . '</p>'
        . '<p><a class="cta" href="' . e(url('evento/' . $c['slug'])) . '">Volver al evento</a></p>';
}
$h .= (setting('contact_email') ? '<p class="small noprint">¿Alguna duda? Escríbenos a <a href="mailto:' . e(setting('contact_email')) . '">' . e(setting('contact_email')) . '</a>.</p>' : '') . '</section>';
site_page('Tu reserva', $h, ['noindex' => true, 'scripts' => '<script src="' . asset('qrcode.js') . '"></script><script>document.querySelectorAll("[data-qr]").forEach(function(el){var q=qrcode(0,"M");q.addData(el.dataset.qr);q.make();el.innerHTML=q.createSvgTag({cellSize:4,margin:2,scalable:true});});</script>']);
