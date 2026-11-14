<?php
// Logica de negocio: ventas, pagos, entradas, facturas, correo, Stripe y analitica

const ORDER_STATUS = ['pending' => 'Pendiente de pago', 'paid' => 'Pagado', 'cancelled' => 'Cancelado', 'refunded' => 'Devuelto'];
const PAY_METHODS = ['stripe' => 'Tarjeta', 'transfer' => 'Transferencia', 'bizum' => 'Bizum', 'cash' => 'Efectivo', 'tpv' => 'Datáfono', 'invite' => 'Invitación'];
const EVENT_STATUS = ['draft' => 'Borrador', 'published' => 'Publicado', 'closed' => 'Ventas cerradas', 'done' => 'Finalizado', 'cancelled' => 'Cancelado'];

function tt_sold($ttId) {
    return (int)val("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.ticket_type_id=? AND o.status IN ('paid','pending')", [$ttId]);
}
function event_stats($eventId) {
    $r = row("SELECT COALESCE(SUM(CASE WHEN o.status='paid' THEN oi.qty*oi.seats ELSE 0 END),0) AS sold,
                     COALESCE(SUM(CASE WHEN o.status='pending' THEN oi.qty*oi.seats ELSE 0 END),0) AS reserved
              FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.event_id=?", [$eventId]);
    $r['sold'] = (int)$r['sold']; $r['reserved'] = (int)$r['reserved'];
    $r['revenue'] = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE event_id=? AND status='paid'", [$eventId]);
    $r['pending'] = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE event_id=? AND status='pending'", [$eventId]);
    $r['checked'] = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id=? AND status='used'", [$eventId]);
    return $r;
}
// Plazas libres de un evento (null = sin limite)
function event_left(array $ev) {
    if ((int)$ev['capacity'] <= 0) return null;
    $s = event_stats($ev['id']);
    return max(0, (int)$ev['capacity'] - $s['sold'] - $s['reserved']);
}
function tt_left(array $tt, array $ev) {
    $left = (int)$tt['quota'] > 0 ? max(0, (int)$tt['quota'] - tt_sold($tt['id'])) : null;
    $el = event_left($ev);
    if ($el !== null) { $el = intdiv($el, max(1, (int)$tt['seats'])); $left = $left === null ? $el : min($left, $el); }
    return $left;
}
function tt_on_sale(array $tt) {
    $n = now();
    return $tt['active'] && (!$tt['sales_start'] || $tt['sales_start'] <= $n) && (!$tt['sales_end'] || $tt['sales_end'] >= $n);
}
// Libera las reservas que no se han pagado a tiempo
function expire_orders() {
    $hold = max(1, (int)setting('hold_hours', 72));
    q("UPDATE orders SET status='cancelled' WHERE status='pending' AND ((method='stripe' AND created_at<?) OR (method<>'stripe' AND created_at<?))",
        [date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() - $hold * 3600)]);
}
function find_discount($code, $eventId) {
    $code = strtoupper(trim((string)$code));
    if ($code === '') return null;
    $d = row('SELECT * FROM discount_codes WHERE code=? AND active=1', [$code]);
    if (!$d) return null;
    if ($d['event_id'] && (int)$d['event_id'] !== (int)$eventId) return null;
    if ($d['valid_until'] && $d['valid_until'] < date('Y-m-d')) return null;
    if ((int)$d['max_uses'] > 0 && (int)$d['used'] >= (int)$d['max_uses']) return null;
    return $d;
}

/**
 * Crea un pedido. $items = [ticket_type_id => cantidad]. Lanza RuntimeException con un mensaje legible si algo no cuadra.
 * $o: admin (salta ventana de venta y limite por pedido), paid (marcar pagado), utm, notes, invoice_*
 */
function create_order(array $ev, array $items, array $cust, $method, $code = '', array $o = []) {
    $admin = !empty($o['admin']);
    $items = array_filter(array_map('intval', $items), fn($n) => $n > 0);
    if (!$items) throw new RuntimeException('Elige al menos una entrada.');
    $name = trim((string)($cust['name'] ?? '')); $email = strtolower(trim((string)($cust['email'] ?? '')));
    if (mb_strlen($name) < 2) throw new RuntimeException('Indica tu nombre y apellidos.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Revisa el correo electrónico, parece que no es correcto.');
    if (!$admin && ($ev['status'] !== 'published' || $ev['starts_at'] < now())) throw new RuntimeException('Este evento ya no admite reservas.');
    if (!isset(PAY_METHODS[$method])) throw new RuntimeException('Elige una forma de pago.');

    $pdo = db(); $pdo->beginTransaction();
    try {
        if (is_mysql()) q('SELECT id FROM events WHERE id=? FOR UPDATE', [$ev['id']]);
        expire_orders();
        $lines = []; $subtotal = 0; $totalQty = 0;
        foreach ($items as $ttId => $qty) {
            $tt = row('SELECT * FROM ticket_types WHERE id=? AND event_id=?', [$ttId, $ev['id']]);
            if (!$tt) throw new RuntimeException('Una de las entradas elegidas ya no existe.');
            if (!$admin && !tt_on_sale($tt)) throw new RuntimeException('La entrada "' . $tt['name'] . '" no está a la venta en este momento.');
            if (!$admin && (int)$tt['max_per_order'] > 0 && $qty > (int)$tt['max_per_order']) throw new RuntimeException('Máximo ' . (int)$tt['max_per_order'] . ' entradas "' . $tt['name'] . '" por reserva.');
            $left = tt_left($tt, $ev);
            if ($left !== null && $qty > $left) throw new RuntimeException($left > 0 ? 'Solo quedan ' . $left . ' plazas de "' . $tt['name'] . '".' : 'Las entradas "' . $tt['name'] . '" se han agotado.');
            $price = $method === 'invite' ? 0 : (float)$tt['price'];
            $seats = max(1, (int)$tt['seats']);
            $lines[] = ['ticket_type_id' => $tt['id'], 'name' => $tt['name'], 'qty' => $qty, 'seats' => $seats, 'unit_price' => $price];
            $subtotal += $price * $qty; $totalQty += $qty * $seats;
        }
        $el = event_left($ev);
        if ($el !== null && $totalQty > $el) throw new RuntimeException($el > 0 ? 'Solo quedan ' . $el . ' plazas para este evento.' : 'Este evento está completo.');
        $discount = 0; $usedCode = '';
        if (trim((string)$code) !== '') {
            $d = find_discount($code, $ev['id']);
            if (!$d) throw new RuntimeException('El código promocional no es válido o ha caducado.');
            $discount = $d['kind'] === 'pct' ? round($subtotal * (float)$d['amount'] / 100, 2) : min($subtotal, (float)$d['amount']);
            $usedCode = $d['code'];
            q('UPDATE discount_codes SET used=used+1 WHERE id=?', [$d['id']]);
        }
        $total = round($subtotal - $discount, 2);

        $c = row('SELECT * FROM customers WHERE email=?', [$email]);
        $cd = ['name' => $name, 'phone' => trim((string)($cust['phone'] ?? ''))];
        if (!empty($cust['marketing_ok'])) $cd['marketing_ok'] = 1;
        if ($c) { if ($cd['phone'] === '') unset($cd['phone']); update('customers', $cd, $c['id']); $cid = $c['id']; }
        else $cid = insert('customers', $cd + ['email' => $email, 'source' => $admin ? 'taquilla' : 'compra', 'created_at' => now()]);

        $utm = $o['utm'] ?? [];
        $oid = insert('orders', [
            'token' => token(16), 'event_id' => $ev['id'], 'customer_id' => $cid, 'status' => 'pending', 'method' => $method,
            'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'discount_code' => $usedCode,
            'utm_source' => $utm['source'] ?? '', 'utm_medium' => $utm['medium'] ?? '', 'utm_campaign' => $utm['campaign'] ?? '',
            'invoice_name' => $o['invoice_name'] ?? '', 'invoice_tax_id' => $o['invoice_tax_id'] ?? '', 'invoice_address' => $o['invoice_address'] ?? '',
            'notes' => $o['notes'] ?? '', 'created_by' => $admin ? (user()['id'] ?? null) : null, 'created_at' => now(),
        ]);
        q('UPDATE orders SET num=? WHERE id=?', ['P' . date('y') . '-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT), $oid]);
        foreach ($lines as $l) insert('order_items', $l + ['order_id' => $oid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

    if ($total <= 0 || !empty($o['paid'])) mark_paid($oid, $o['pay_ref'] ?? '');
    elseif ($method !== 'stripe') mail_order_pending($oid);
    return row('SELECT * FROM orders WHERE id=?', [$oid]);
}

function mark_paid($orderId, $ref = '') {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $o = row('SELECT * FROM orders WHERE id=?' . (is_mysql() ? ' FOR UPDATE' : ''), [$orderId]);
        if (!$o || $o['status'] === 'paid' || $o['status'] === 'refunded') { $pdo->commit(); return false; }
        q("UPDATE orders SET status='paid', paid_at=?, pay_ref=? WHERE id=?", [now(), mb_substr((string)$ref, 0, 250), $orderId]);
        $cname = (string)val('SELECT name FROM customers WHERE id=?', [$o['customer_id']]);
        if (!val('SELECT COUNT(*) FROM tickets WHERE order_id=?', [$orderId])) {
            foreach (rows('SELECT * FROM order_items WHERE order_id=?', [$orderId]) as $it) {
                for ($i = 0; $i < (int)$it['qty'] * max(1, (int)$it['seats']); $i++) {
                    insert('tickets', ['code' => strtoupper(token(8)), 'order_id' => $orderId, 'event_id' => $o['event_id'], 'ticket_type_id' => $it['ticket_type_id'],
                        'type_name' => $it['name'], 'holder_name' => $cname, 'status' => 'valid']);
                }
            }
        } else q("UPDATE tickets SET status='valid' WHERE order_id=? AND status='void'", [$orderId]);
        if ((float)$o['total'] > 0) create_invoice(row('SELECT * FROM orders WHERE id=?', [$orderId]), $cname);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    mail_order_paid($orderId);
    return true;
}

function next_invoice_num($serie) {
    $yr = (int)date('Y');
    $seq = (int)val('SELECT COALESCE(MAX(seq),0) FROM invoices_out WHERE serie=? AND yr=?', [$serie, $yr]) + 1;
    return [$serie . $yr . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT), $yr, $seq];
}
function create_invoice(array $o, $cname, $negativeOf = null) {
    $ev = row('SELECT title, tax_pct, starts_at FROM events WHERE id=?', [$o['event_id']]);
    $pct = (float)($ev['tax_pct'] ?? 21); $sign = $negativeOf ? -1 : 1;
    $total = $sign * (float)$o['total']; $base = round($total / (1 + $pct / 100), 2);
    [$num, $yr, $seq] = next_invoice_num($negativeOf ? 'R' : 'F');
    return insert('invoices_out', ['num' => $num, 'serie' => $negativeOf ? 'R' : 'F', 'yr' => $yr, 'seq' => $seq, 'order_id' => $o['id'], 'ref_invoice_id' => $negativeOf,
        'issued_at' => date('Y-m-d'), 'customer_name' => $o['invoice_name'] ?: $cname, 'tax_id' => $o['invoice_tax_id'], 'address' => $o['invoice_address'],
        'concept' => 'Entradas ' . ($ev['title'] ?? '') . ' (' . fdate($ev['starts_at'] ?? '') . ')', 'base' => $base, 'tax_pct' => $pct, 'tax' => round($total - $base, 2), 'total' => $total]);
}
function cancel_order($orderId) {
    $o = row('SELECT * FROM orders WHERE id=?', [$orderId]);
    if (!$o) return false;
    if ($o['status'] === 'pending') { q("UPDATE orders SET status='cancelled' WHERE id=?", [$orderId]); return true; }
    if ($o['status'] !== 'paid') return false;
    q("UPDATE orders SET status='refunded' WHERE id=?", [$orderId]);
    q("UPDATE tickets SET status='void' WHERE order_id=?", [$orderId]);
    $inv = row("SELECT * FROM invoices_out WHERE order_id=? AND serie='F' ORDER BY id DESC", [$orderId]);
    if ($inv) create_invoice($o, $inv['customer_name'], $inv['id']);
    return true;
}

/* ---------- Correo ---------- */
function send_mail($to, $subject, $html) {
    if (!empty($GLOBALS['NO_MAIL'])) return false;
    $accent = setting('accent', '#a8665a');
    $body = '<div style="background:#f5f2ee;padding:32px 12px;font-family:-apple-system,Helvetica,Arial,sans-serif;color:#1d1d1f">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:18px;padding:36px 32px">'
        . '<div style="font-family:Georgia,serif;font-size:22px;letter-spacing:.02em;margin-bottom:24px;color:' . e($accent) . '">' . e(brand()) . '</div>'
        . '<div style="font-size:16px;line-height:1.6">' . $html . '</div></div>'
        . '<div style="max-width:560px;margin:16px auto 0;font-size:12px;color:#86868b;text-align:center">' . e(setting('legal_name', brand())) . '</div></div>';
    $ok = false;
    if (!cfg('mail_off')) {
        $from = setting('mail_from', 'no-reply@' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?" . base64_encode(brand()) . "?= <$from>";
        if (setting('contact_email')) $h .= "\r\nReply-To: " . setting('contact_email');
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $h);
    }
    insert('emails', ['to_email' => $to, 'subject' => $subject, 'body' => $html, 'sent' => $ok ? 1 : 0, 'created_at' => now()]);
    return $ok;
}
function mail_btn($url, $label) {
    return '<p style="margin:28px 0"><a href="' . e($url) . '" style="background:#1d1d1f;color:#fff;text-decoration:none;padding:14px 28px;border-radius:999px;font-weight:600;display:inline-block">' . e($label) . '</a></p>';
}
function order_ctx($orderId) {
    return row('SELECT o.*, c.name AS cname, c.email AS cemail, e.title, e.starts_at, e.slug, v.name AS vname, v.address AS vaddress, v.city AS vcity
                FROM orders o JOIN customers c ON c.id=o.customer_id JOIN events e ON e.id=o.event_id LEFT JOIN venues v ON v.id=e.venue_id WHERE o.id=?', [$orderId]);
}
function first_name($n) { return explode(' ', trim((string)$n))[0]; }
function pay_instructions(array $o) {
    if ($o['method'] === 'transfer') return '<p>Haz una transferencia de <b>' . money($o['total']) . '</b> a la cuenta <b>' . e(setting('iban', '(IBAN pendiente de configurar)')) . '</b>'
        . (setting('legal_name') ? ' a nombre de ' . e(setting('legal_name')) : '') . ', indicando en el concepto <b>' . e($o['num']) . '</b>.</p>';
    if ($o['method'] === 'bizum') return '<p>Envía un Bizum de <b>' . money($o['total']) . '</b> al <b>' . e(setting('bizum', '(número pendiente de configurar)')) . '</b> indicando en el concepto <b>' . e($o['num']) . '</b>.</p>';
    return '';
}
function mail_order_pending($orderId) {
    $o = order_ctx($orderId); if (!$o) return;
    send_mail($o['cemail'], 'Tu reserva para ' . $o['title'] . ' está casi lista',
        '<p>Hola ' . e(first_name($o['cname'])) . ':</p><p>Hemos guardado tus plazas para <b>' . e($o['title']) . '</b> (' . e(fecha_larga($o['starts_at'], true)) . ').</p>'
        . pay_instructions($o) . '<p>Guardamos tu reserva durante ' . (int)setting('hold_hours', 72) . ' horas. En cuanto confirmemos el pago te enviaremos tus entradas.</p>'
        . mail_btn(abs_url('pedido/' . $o['token']), 'Ver mi reserva'));
}
function mail_order_paid($orderId) {
    $o = order_ctx($orderId); if (!$o) return;
    send_mail($o['cemail'], 'Tus entradas para ' . $o['title'],
        '<p>Hola ' . e(first_name($o['cname'])) . ':</p><p>Tu reserva está confirmada. Te esperamos el <b>' . e(fecha_larga($o['starts_at'], true)) . '</b>'
        . ($o['vname'] ? ' en <b>' . e($o['vname']) . '</b>' . ($o['vaddress'] ? ' (' . e(trim($o['vaddress'] . ', ' . $o['vcity'], ', ')) . ')' : '') : '') . '.</p>'
        . '<p>En el enlace tienes tus entradas con código QR. Solo tienes que enseñarlas en el móvil al llegar.</p>'
        . mail_btn(abs_url('pedido/' . $o['token']), 'Ver mis entradas'));
}
function mail_reminder($orderId) {
    $o = order_ctx($orderId); if (!$o) return;
    send_mail($o['cemail'], 'Nos vemos pronto: ' . $o['title'],
        '<p>Hola ' . e(first_name($o['cname'])) . ':</p><p>Queda muy poco para <b>' . e($o['title']) . '</b>. Te esperamos el <b>' . e(fecha_larga($o['starts_at'], true)) . '</b>'
        . ($o['vname'] ? ' en <b>' . e($o['vname']) . '</b>' : '') . '.</p><p>Ten a mano tus entradas en el móvil para entrar sin esperas.</p>'
        . mail_btn(abs_url('pedido/' . $o['token']), 'Ver mis entradas'));
}
function mail_survey($orderId) {
    $o = order_ctx($orderId); if (!$o) return;
    send_mail($o['cemail'], '¿Qué te pareció ' . $o['title'] . '?',
        '<p>Hola ' . e(first_name($o['cname'])) . ':</p><p>Gracias por acompañarnos en <b>' . e($o['title']) . '</b>. Nos encantaría conocer tu opinión, es menos de un minuto y nos ayuda a preparar los próximos encuentros.</p>'
        . mail_btn(abs_url('opinion/' . $o['token']), 'Dar mi opinión'));
    q('UPDATE orders SET survey_sent=1 WHERE id=?', [$orderId]);
}

/* ---------- Stripe (pago con tarjeta) ---------- */
function stripe_on() { return setting('stripe_secret') !== '' && function_exists('curl_init'); }
function stripe_api($method, $path, array $params = []) {
    $ch = curl_init('https://api.stripe.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => setting('stripe_secret') . ':', CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method]);
    if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    $j = json_decode((string)$res, true);
    if (!is_array($j)) throw new RuntimeException('No se pudo conectar con la pasarela de pago. ' . $err);
    if (isset($j['error'])) throw new RuntimeException('La pasarela de pago ha devuelto un error: ' . ($j['error']['message'] ?? ''));
    return $j;
}
function stripe_checkout_url(array $o) {
    $c = order_ctx($o['id']);
    $s = stripe_api('POST', 'checkout/sessions', [
        'mode' => 'payment', 'client_reference_id' => $o['token'], 'customer_email' => $c['cemail'], 'locale' => 'es',
        'success_url' => abs_url('pedido/' . $o['token'], ['pago' => 'ok']), 'cancel_url' => abs_url('pedido/' . $o['token'], ['pago' => 'cancelado']),
        'expires_at' => time() + 1900,
        'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => (int)round((float)$o['total'] * 100),
            'product_data' => ['name' => 'Entradas ' . $c['title'], 'description' => 'Reserva ' . $o['num']]]]],
    ]);
    q('UPDATE orders SET stripe_session=? WHERE id=?', [$s['id'], $o['id']]);
    return $s['url'];
}
function stripe_sync(array $o) {
    if (!$o['stripe_session'] || $o['status'] !== 'pending' || !stripe_on()) return;
    try {
        $s = stripe_api('GET', 'checkout/sessions/' . rawurlencode($o['stripe_session']));
        if (($s['payment_status'] ?? '') === 'paid') { q('UPDATE orders SET stripe_pi=? WHERE id=?', [(string)($s['payment_intent'] ?? ''), $o['id']]); mark_paid($o['id'], 'Stripe ' . ($s['payment_intent'] ?? '')); }
    } catch (Throwable $e) {}
}
function stripe_webhook() {
    $payload = file_get_contents('php://input'); $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''; $secret = setting('stripe_whsec');
    if ($secret === '') { http_response_code(400); exit('webhook sin configurar'); }
    $t = ''; $v1 = [];
    foreach (explode(',', $sig) as $p) { $kv = explode('=', trim($p), 2); if (count($kv) === 2) { if ($kv[0] === 't') $t = $kv[1]; if ($kv[0] === 'v1') $v1[] = $kv[1]; } }
    $ok = false; $exp = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($v1 as $s) if (hash_equals($exp, $s)) $ok = true;
    if (!$ok || abs(time() - (int)$t) > 600) { http_response_code(400); exit('firma no valida'); }
    $ev = json_decode($payload, true);
    if (($ev['type'] ?? '') === 'checkout.session.completed' && ($ev['data']['object']['payment_status'] ?? '') === 'paid') {
        $s = $ev['data']['object'];
        $o = row('SELECT * FROM orders WHERE token=?', [(string)($s['client_reference_id'] ?? '')]);
        if ($o) { q('UPDATE orders SET stripe_pi=? WHERE id=?', [(string)($s['payment_intent'] ?? ''), $o['id']]); mark_paid($o['id'], 'Stripe ' . ($s['payment_intent'] ?? '')); }
    }
    echo 'ok'; exit;
}

/* ---------- Analitica web propia (sin cookies de terceros) ---------- */
function capture_utm() {
    if (!empty($_GET['utm_source']) && is_string($_GET['utm_source'])) {
        $c = fn($k) => mb_substr(preg_replace('/[^\w\-. ]/u', '', (string)($_GET[$k] ?? '')), 0, 60);
        $_SESSION['utm'] = ['source' => strtolower($c('utm_source')), 'medium' => strtolower($c('utm_medium')), 'campaign' => $c('utm_campaign')];
    }
}
function track($kind, $eventId = null, $path = '') {
    if (user()) return;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless/i', $ua)) return;
    $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?: '';
    if ($ref === preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')) $ref = '';
    $ref = preg_replace('/^(www|l|m|lm)\./', '', $ref);
    $dev = preg_match('/ipad|tablet/i', $ua) ? 'Tablet' : (preg_match('/mobi|iphone|android/i', $ua) ? 'Móvil' : 'Ordenador');
    $utm = $_SESSION['utm'] ?? [];
    try {
        insert('pageviews', ['day' => date('Y-m-d'), 'created_at' => now(), 'kind' => $kind, 'path' => mb_substr($path, 0, 120), 'event_id' => $eventId,
            'visitor' => substr(sha1(client_ip() . $ua . date('Y-m-d') . cfg('secret')), 0, 16), 'referrer' => mb_substr($ref, 0, 80),
            'utm_source' => $utm['source'] ?? '', 'utm_medium' => $utm['medium'] ?? '', 'utm_campaign' => $utm['campaign'] ?? '', 'device' => $dev]);
    } catch (Throwable $e) {}
}
// Expresion SQL que resume el canal de origen de una visita o pedido
function channel_label($src, $ref = '') {
    $s = strtolower(trim((string)$src)) ?: strtolower(trim((string)$ref));
    if ($s === '') return 'Directo';
    foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb' => 'Facebook', 'tiktok' => 'TikTok', 'google' => 'Google', 'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp',
        'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'newsletter' => 'Newsletter', 'email' => 'Email', 't.co' => 'X', 'twitter' => 'X', 'bing' => 'Bing'] as $k => $l) {
        if (str_contains($s, $k)) return $l;
    }
    return ucfirst($s);
}

/* ---------- Listas de valores ---------- */
function opts(array $l) { return array_combine($l, $l); }
const EVENT_CATS = ['Cena', 'Brunch', 'Cata', 'Taller', 'Experiencia', 'Bienestar', 'Networking', 'Moda y belleza', 'Otro'];
const SUP_CATS = ['Catering', 'Mobiliario y menaje', 'Mantelería y textil', 'Floristería', 'Decoración', 'Iluminación y sonido', 'Fotografía y vídeo', 'Música y animación', 'Personal de sala', 'Montaje y logística', 'Limpieza', 'Imprenta', 'Transporte', 'Otros'];
const SETUP_CATS = ['Mobiliario', 'Mesas y sillas', 'Mantelería y vestido de mesas', 'Menaje y cristalería', 'Decoración floral', 'Decoración', 'Iluminación', 'Sonido', 'Cartelería', 'Catering', 'Otros'];
const SETUP_STATUS = ['pendiente' => 'Pendiente', 'pedido' => 'Pedido', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado', 'montado' => 'Montado', 'recogido' => 'Recogido'];
const SETUP_BADGE = ['pendiente' => 'orange', 'pedido' => 'blue', 'confirmado' => 'blue', 'entregado' => 'green', 'montado' => 'green', 'recogido' => 'gray'];
const PHASES = ['organizacion' => 'Organización', 'promocion' => 'Promoción', 'montaje' => 'Montaje', 'evento' => 'Día del evento', 'postventa' => 'Postventa'];
const PLATFORMS = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'x' => 'X', 'newsletter' => 'Newsletter', 'google' => 'Google'];
const EVENT_BADGE = ['draft' => 'gray', 'published' => 'green', 'closed' => 'orange', 'done' => 'blue', 'cancelled' => 'red'];
const ORDER_BADGE = ['pending' => 'orange', 'paid' => 'green', 'cancelled' => 'gray', 'refunded' => 'red'];
const TASK_TEMPLATE = [
    ['organizacion', 'Confirmar fecha y reservar el local', -45], ['organizacion', 'Cerrar el acuerdo con el partner', -40], ['organizacion', 'Definir aforo, precio y tipos de entrada', -38], ['organizacion', 'Contratar proveedores y pedir presupuestos', -30],
    ['promocion', 'Preparar fotos y textos del evento', -35], ['promocion', 'Publicar el evento en la web', -30], ['promocion', 'Anuncio principal en Instagram', -28], ['promocion', 'Recordatorio en stories', -7], ['promocion', 'Aviso de últimas plazas', -3],
    ['montaje', 'Confirmar mobiliario, mantelería y menaje', -10], ['montaje', 'Confirmar personal contratado', -7], ['montaje', 'Plano de mesas y distribución de la sala', -5], ['montaje', 'Montaje y vestido de mesas', 0],
    ['evento', 'Preparar el acceso y el lector de entradas QR', 0], ['evento', 'Recepción de asistentes', 0], ['evento', 'Fotos y contenido para redes', 0],
    ['postventa', 'Recogida y desmontaje', 1], ['postventa', 'Enviar la encuesta de opinión', 1], ['postventa', 'Publicar fotos y agradecimiento', 2], ['postventa', 'Revisar facturas y liquidar al partner', 7],
];
const POST_STATUS = ['idea' => 'Idea', 'borrador' => 'En preparación', 'programado' => 'Programado', 'publicado' => 'Publicado'];
const POST_BADGE = ['idea' => 'gray', 'borrador' => 'orange', 'programado' => 'blue', 'publicado' => 'green'];
const POST_FORMATS = ['Publicación', 'Reel', 'Historia', 'Carrusel', 'Anuncio de pago', 'Directo', 'Mensaje o email'];
// Formas de pago que se ofrecen en la web
function public_methods() {
    $m = [];
    if (stripe_on()) $m['stripe'] = 'Tarjeta, Apple Pay o Google Pay';
    if (setting('pay_bizum') === '1' && setting('bizum') !== '') $m['bizum'] = 'Bizum';
    if (setting('pay_transfer') === '1' && setting('iban') !== '') $m['transfer'] = 'Transferencia bancaria';
    return $m;
}
function event_public(array $ev) { return $ev['status'] === 'published' && $ev['starts_at'] >= now(); }
function min_price($eventId) { $p = val('SELECT MIN(price) FROM ticket_types WHERE event_id=? AND active=1', [$eventId]); return $p === null ? null : (float)$p; }

/* ---------- Acciones compartidas por el panel y por el acceso de IA (una sola implementacion) ---------- */
const PAYOUT_METHODS = ['transfer' => 'Transferencia', 'bizum' => 'Bizum', 'cash' => 'Efectivo', 'card' => 'Tarjeta', 'domiciliado' => 'Domiciliación'];

function checkin_counts($eventId) {
    return ['in' => (int)val("SELECT COUNT(*) FROM tickets WHERE event_id=? AND status='used'", [$eventId]), 'all' => (int)val("SELECT COUNT(*) FROM tickets WHERE event_id=? AND status<>'void'", [$eventId])];
}
// Valida (o deshace) una entrada. $raw puede ser el codigo o la direccion completa del QR.
function checkin_scan($raw, $evId = 0, $undo = false) {
    $raw = trim((string)$raw);
    if (preg_match('#/c/([A-Za-z0-9]+)#', $raw, $m)) $raw = $m[1];
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    $t = $code !== '' ? row('SELECT t.*, e.title FROM tickets t JOIN events e ON e.id=t.event_id WHERE t.code=?', [$code]) : null;
    $out = ['ok' => false, 'level' => 'err', 'title' => 'Entrada no encontrada', 'text' => 'Este código no corresponde a ninguna entrada. Comprueba que se ha leído bien.'];
    if (!$t) return $out;
    $who = $t['holder_name'] . ' · ' . $t['type_name'];
    if ($undo) { q("UPDATE tickets SET status='valid', checked_in_at=NULL, checked_in_by=NULL WHERE id=? AND status='used'", [$t['id']]); $out = ['ok' => true, 'level' => 'warn', 'title' => 'Entrada deshecha', 'text' => $who . '. Vuelve a estar pendiente de entrar.']; }
    elseif ($evId && (int)$t['event_id'] !== (int)$evId) $out = ['ok' => false, 'level' => 'err', 'title' => 'Entrada de otro evento', 'text' => 'Esta entrada es para "' . $t['title'] . '".'];
    elseif ($t['status'] === 'void') $out = ['ok' => false, 'level' => 'err', 'title' => 'Entrada anulada', 'text' => $who . '. La reserva fue cancelada o devuelta.'];
    elseif ($t['status'] === 'used') $out = ['ok' => false, 'level' => 'warn', 'title' => 'Ya había entrado', 'text' => $who . '. Se validó el ' . fdt($t['checked_in_at']) . '.', 'code' => $t['code']];
    else {
        $n = q("UPDATE tickets SET status='used', checked_in_at=?, checked_in_by=? WHERE id=? AND status='valid'", [now(), user()['id'], $t['id']])->rowCount();
        $out = $n ? ['ok' => true, 'level' => 'ok', 'title' => 'Adelante', 'text' => $who, 'code' => $t['code']] : ['ok' => false, 'level' => 'warn', 'title' => 'Ya había entrado', 'text' => $who];
    }
    $out['counts'] = checkin_counts($t['event_id']);
    return $out;
}
// Devuelve una reserva pagada. Resultado: [bool correcto, string mensaje]
function refund_order(array $o) {
    if ($o['status'] !== 'paid') return [false, 'Solo se pueden devolver reservas pagadas.'];
    $msg = 'Reserva devuelta: entradas anuladas y factura rectificativa emitida.';
    if ($o['method'] === 'stripe' && $o['stripe_pi'] && stripe_on()) {
        try { stripe_api('POST', 'refunds', ['payment_intent' => $o['stripe_pi']]); $msg .= ' El importe se ha devuelto a la tarjeta.'; }
        catch (Throwable $e) { return [false, 'No se pudo devolver el cobro en la pasarela: ' . $e->getMessage() . ' La reserva no se ha modificado.']; }
    } else $msg .= ' Recuerda devolver el dinero a la clienta por el mismo medio en que pagó.';
    cancel_order($o['id']); log_activity('Reserva devuelta', $o['num']);
    return [true, $msg];
}
function settlement_calc(array $ev) {
    $rev = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE event_id=? AND status='paid'", [$ev['id']]);
    $gross = round($rev / (1 + (float)$ev['tax_pct'] / 100), 2);
    $pct = (float)$ev['partner_share_pct'] > 0 ? (float)$ev['partner_share_pct'] : (float)val('SELECT share_pct FROM partners WHERE id=?', [$ev['partner_id']]);
    return ['gross' => $gross, 'share_pct' => $pct, 'fee' => (float)$ev['partner_fee'], 'amount' => round($gross * $pct / 100 + (float)$ev['partner_fee'], 2)];
}
// Genera la liquidacion de un evento con partner. Devuelve su id, o null si no procede o ya existe.
function make_settlement($eventId) {
    $ev = row('SELECT * FROM events WHERE id=? AND partner_id IS NOT NULL', [(int)$eventId]);
    if (!$ev || val('SELECT COUNT(*) FROM settlements WHERE event_id=?', [$ev['id']])) return null;
    $id = insert('settlements', settlement_calc($ev) + ['partner_id' => $ev['partner_id'], 'event_id' => $ev['id'], 'status' => 'pending', 'created_at' => now()]);
    log_activity('Liquidación generada', $ev['title']);
    return $id;
}
function payment_row($amount, $date, $method, $ref) {
    return ['amount' => $amount, 'paid_at' => $date ?: date('Y-m-d'), 'method' => isset(PAYOUT_METHODS[$method]) ? $method : 'transfer', 'reference' => (string)$ref, 'user_id' => user()['id'] ?? null, 'created_at' => now()];
}
function pay_supplier_invoice(array $inv, $amount, $date = '', $method = 'transfer', $ref = '') {
    $amount = round((float)$amount, 2); if ($amount <= 0) return false;
    insert('payments', payment_row($amount, $date, $method, $ref) + ['kind' => 'supplier', 'supplier_invoice_id' => $inv['id']]);
    $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE supplier_invoice_id=?', [$inv['id']]);
    update('supplier_invoices', ['paid' => $paid, 'status' => $paid >= (float)$inv['total'] - 0.005 ? 'paid' : 'partial'], $inv['id']);
    log_activity('Pago a proveedor', money($amount) . ' · factura ' . $inv['num']);
    return true;
}
function pay_settlement(array $s, $amount, $date = '', $method = 'transfer', $ref = '') {
    $amount = round((float)$amount, 2); if ($amount <= 0) return false;
    insert('payments', payment_row($amount, $date, $method, $ref) + ['kind' => 'partner', 'settlement_id' => $s['id']]);
    $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE settlement_id=?', [$s['id']]);
    update('settlements', ['paid' => $paid, 'status' => $paid >= (float)$s['amount'] - 0.005 ? 'paid' : 'pending'], $s['id']);
    log_activity('Pago a partner', money($amount));
    return true;
}
// Cambia el estado de un evento. Devuelve un mensaje de error o null si todo va bien.
function event_set_status(array $ev, $status) {
    if (!isset(EVENT_STATUS[$status])) return 'Estado no válido.';
    if ($status === 'published' && !val('SELECT COUNT(*) FROM ticket_types WHERE event_id=? AND active=1', [$ev['id']])) return 'Antes de publicar, añade al menos un tipo de entrada.';
    update('events', ['status' => $status], $ev['id']);
    log_activity('Evento: ' . EVENT_STATUS[$status], $ev['title']);
    return null;
}
function event_load_tasks(array $ev) {
    $t0 = strtotime($ev['starts_at']);
    foreach (TASK_TEMPLATE as $t) insert('tasks', ['event_id' => $ev['id'], 'phase' => $t[0], 'title' => $t[1], 'due_date' => date('Y-m-d', $t0 + $t[2] * 86400), 'assignee_id' => $ev['manager_id']]);
    return count(TASK_TEMPLATE);
}
// Envia recordatorio ('remind') o encuesta ('survey') a las reservas pagadas. Devuelve cuantos correos salen.
function event_notify($eventId, $kind) {
    $n = 0;
    foreach (rows("SELECT id, survey_sent FROM orders WHERE event_id=? AND status='paid'", [$eventId]) as $o) {
        if ($kind === 'remind') { mail_reminder($o['id']); $n++; }
        elseif (!$o['survey_sent']) { mail_survey($o['id']); $n++; }
    }
    return $n;
}
