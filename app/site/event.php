<?php
capture_utm(); expire_orders();
$ev = row('SELECT e.*, v.name AS vname, v.address AS vaddress, v.city, v.maps_url, p.name AS pname, p.speciality, p.instagram AS pig FROM events e LEFT JOIN venues v ON v.id=e.venue_id LEFT JOIN partners p ON p.id=e.partner_id WHERE e.slug=?', [$seg[1] ?? '']);
if (!$ev || $ev['status'] === 'cancelled' || ($ev['status'] === 'draft' && !can('events'))) not_found();
track('view', $ev['id'], 'evento/' . $ev['slug']);
$onSale = event_public($ev); $methods = public_methods(); $old = $_SESSION['old'] ?? []; unset($_SESSION['old']);
$types = rows('SELECT * FROM ticket_types WHERE event_id=? AND active=1 ORDER BY sort, id', [$ev['id']]);
$left = event_left($ev);

$h = ($ev['status'] === 'draft' ? '<div class="notice">Vista previa. Este evento todavía no está publicado y solo lo ve el equipo.</div>' : '')
    . '<article class="ev">' . event_art($ev, 'evhero') . '<div class="wrap evbody"><div class="evmain">'
    . ($ev['category'] ? '<p class="eyebrow">' . e($ev['category']) . '</p>' : '') . '<h1>' . e($ev['title']) . '</h1>' . ($ev['subtitle'] ? '<p class="lead">' . e($ev['subtitle']) . '</p>' : '')
    . '<ul class="facts"><li><span>Cuándo</span><b>' . e(ucfirst(fecha_larga($ev['starts_at'], true))) . '</b></li>'
    . ($ev['vname'] ? '<li><span>Dónde</span><b>' . e($ev['vname']) . '</b>' . ($ev['vaddress'] || $ev['city'] ? '<em>' . e(trim($ev['vaddress'] . ', ' . $ev['city'], ', ')) . '</em>' : '') . ($ev['maps_url'] ? '<a href="' . e($ev['maps_url']) . '" target="_blank" rel="noopener">Cómo llegar</a>' : '') . '</li>' : '')
    . ($ev['pname'] ? '<li><span>Con</span><b>' . e($ev['pname']) . '</b>' . ($ev['speciality'] ? '<em>' . e($ev['speciality']) . '</em>' : '') . '</li>' : '') . '</ul>'
    . ($ev['description'] ? '<div class="prose">' . nl2br(e($ev['description'])) . '</div>' : '');
$inc = array_filter(array_map('trim', explode("\n", (string)$ev['includes'])));
if ($inc) $h .= '<h2>Qué incluye</h2><ul class="incl">' . implode('', array_map(fn($l) => '<li>' . e($l) . '</li>', $inc)) . '</ul>';
$h .= '</div><aside class="book" id="reservar">';

if (!$onSale || !$types || $left === 0) {
    $msg = $left === 0 && $onSale ? ['Completo', 'Se han agotado las plazas para esta fecha.'] : ($ev['starts_at'] < now() || $ev['status'] === 'done' ? ['Este evento ya se celebró', 'Gracias a todas las que vinisteis.'] : ['Reservas cerradas', 'Ahora mismo no se pueden hacer reservas para esta fecha.']);
    $h .= '<div class="bookbox"><h2>' . $msg[0] . '</h2><p>' . $msg[1] . '</p><p>Mira <a href="' . e(url('')) . '#eventos">los próximos eventos</a> o déjanos tu correo en la portada y te avisamos de nuevas fechas.</p></div>';
} else {
    $h .= '<form method="post" action="' . e(url('comprar')) . '" class="bookbox" id="bookform">' . csrf_field() . '<input type="hidden" name="event_id" value="' . (int)$ev['id'] . '"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">'
        . '<h2>Reserva tu plaza</h2>' . implode('', array_map(fn($f) => '<div class="notice ' . e($f[0]) . '">' . e($f[1]) . '</div>', take_flash())) . ($left !== null && $left <= max(3, (int)$ev['capacity'] * 0.15) ? '<p class="few">Quedan solo ' . $left . ' plazas</p>' : '');
    foreach ($types as $tt) {
        $tl = tt_left($tt, $ev); $ok = tt_on_sale($tt) && $tl !== 0; $max = min((int)$tt['max_per_order'] ?: 10, $tl ?? 99);
        $h .= '<div class="tt' . ($ok ? '' : ' off') . '"><div><b>' . e($tt['name']) . '</b>' . ($tt['description'] ? '<span>' . e($tt['description']) . '</span>' : '') . '<em>' . ((float)$tt['price'] > 0 ? money($tt['price']) : 'Gratuita') . '</em></div>'
            . ($ok ? '<div class="step" data-price="' . (float)$tt['price'] . '" data-max="' . $max . '"><button type="button" class="minus" aria-label="Quitar una">−</button><input type="number" name="qty[' . (int)$tt['id'] . ']" value="' . (int)($old['qty'][$tt['id']] ?? 0) . '" min="0" max="' . $max . '" readonly aria-label="Cantidad de ' . e($tt['name']) . '"><button type="button" class="plus" aria-label="Añadir una">+</button></div>'
                : '<span class="soldout">' . ($tl === 0 ? 'Agotada' : 'No disponible') . '</span>') . '</div>';
    }
    $h .= '<label><span>Nombre y apellidos</span><input name="name" required autocomplete="name" value="' . e($old['name'] ?? '') . '"></label>'
        . '<label><span>Correo electrónico</span><input type="email" name="email" required autocomplete="email" value="' . e($old['email'] ?? '') . '"><small>Aquí recibirás tus entradas.</small></label>'
        . '<label><span>Teléfono</span><input type="tel" name="phone" autocomplete="tel" value="' . e($old['phone'] ?? '') . '"></label>'
        . '<details' . (!empty($old['code']) ? ' open' : '') . '><summary>Tengo un código promocional</summary><label><input name="code" value="' . e($old['code'] ?? '') . '" placeholder="Escribe tu código" autocapitalize="characters" aria-label="Código promocional"></label></details>'
        . '<details' . (!empty($old['invoice_tax_id']) ? ' open' : '') . '><summary>Necesito factura con mis datos fiscales</summary><label><span>Nombre o razón social</span><input name="invoice_name" value="' . e($old['invoice_name'] ?? '') . '"></label><label><span>NIF / CIF</span><input name="invoice_tax_id" value="' . e($old['invoice_tax_id'] ?? '') . '"></label><label><span>Dirección fiscal</span><input name="invoice_address" value="' . e($old['invoice_address'] ?? '') . '"></label></details>';
    if (count($methods) > 1) {
        $h .= '<fieldset class="pay"><legend>Forma de pago</legend>'; $first = $old['method'] ?? array_key_first($methods);
        foreach ($methods as $k => $l) $h .= '<label><input type="radio" name="method" value="' . $k . '"' . ($k === $first ? ' checked' : '') . '><span>' . e($l) . '</span></label>';
        $h .= '</fieldset>';
    } elseif ($methods) $h .= '<input type="hidden" name="method" value="' . array_key_first($methods) . '"><p class="paynote">Pago por ' . e(mb_strtolower(reset($methods))) . '.</p>';
    $h .= '<label class="consent"><input type="checkbox" name="terms" value="1" required> <span>He leído y acepto las <a href="' . e(url('legal/condiciones')) . '" target="_blank">condiciones</a> y la <a href="' . e(url('legal/privacidad')) . '" target="_blank">política de privacidad</a>.</span></label>'
        . '<label class="consent"><input type="checkbox" name="marketing_ok" value="1"' . (!empty($old['marketing_ok']) ? ' checked' : '') . '> <span>Quiero enterarme de los próximos eventos.</span></label>'
        . '<div class="sum"><span>Total</span><b id="total">0,00 €</b></div><button class="cta wide" id="buybtn" disabled>Elige tus entradas</button><p class="safe">Recibirás la confirmación por correo al momento.</p></form>';
}
$h .= '</aside></div></article>' . ($onSale && $types && $left !== 0 ? '<a class="stickybar" href="#reservar"><span>' . (($mp = min_price($ev['id'])) > 0 ? 'Desde ' . money($mp) : 'Gratuito') . '</span><b>Reservar</b></a>' : '');
site_page($ev['title'], $h, ['desc' => $ev['subtitle'] ?: mb_strimwidth((string)$ev['description'], 0, 150, '…'), 'image' => $ev['image'] ? abs_url('uploads/' . $ev['image']) : '', 'noindex' => $ev['status'] === 'draft']);
