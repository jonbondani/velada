<?php
capture_utm(); expire_orders(); track('view', null, 'inicio');
$evs = rows("SELECT e.*, v.name AS vname, v.city FROM events e LEFT JOIN venues v ON v.id=e.venue_id WHERE e.status='published' AND e.starts_at>=? ORDER BY e.starts_at", [now()]);
$card = function (array $ev, $feature = false) {
    $left = event_left($ev); $price = min_price($ev['id']);
    $tag = $left === 0 ? '<span class="tag dark">Completo</span>' : ($left !== null && $left <= max(3, (int)$ev['capacity'] * 0.15) ? '<span class="tag">Últimas ' . $left . ' plazas</span>' : '');
    return '<a class="evcard' . ($feature ? ' feature' : '') . '" href="' . e(url('evento/' . $ev['slug'])) . '">' . event_art($ev) . $tag . '<div class="in"><p class="eyebrow">' . e(fecha_larga($ev['starts_at'], true)) . '</p><h3>' . e($ev['title']) . '</h3>'
        . ($ev['subtitle'] ? '<p>' . e($ev['subtitle']) . '</p>' : '') . '<div class="meta"><span>' . e(trim(($ev['vname'] ?? '') . ($ev['city'] ? ', ' . $ev['city'] : ''), ', ')) . '</span>' . ($price !== null ? '<b>' . ($price > 0 ? 'Desde ' . money($price) : 'Gratuito') . '</b>' : '') . '</div></div></a>';
};
$h = '<section class="hero"><div class="wrap"><p class="eyebrow">' . e(setting('tagline', 'Encuentros con encanto para disfrutar sin prisas')) . '</p><h1>' . e(setting('hero_title', 'Momentos que apetece repetir')) . '</h1>'
    . '<p class="lead">' . e(setting('hero_text', 'Cenas, catas y talleres en lugares con alma, para pocas personas y cuidados hasta el último detalle. Elige tu fecha y nosotras nos ocupamos del resto.')) . '</p>'
    . '<a class="cta" href="#eventos">Ver próximos eventos</a></div></section>';
$h .= '<section id="eventos" class="wrap sec"><h2>Próximos eventos</h2>';
if ($evs) { $h .= '<div class="evgrid">'; foreach ($evs as $i => $ev) $h .= $card($ev, $i === 0 && count($evs) !== 2); $h .= '</div>'; }
else $h .= '<div class="soon"><p class="lead">Estamos preparando nuevas fechas.</p><p>Déjanos tu correo y serás de las primeras en enterarte.</p></div>';
$h .= '</section>';
$h .= '<section id="nosotras" class="about"><div class="wrap narrow"><p class="eyebrow">Sobre nosotras</p><h2>' . e(setting('about_title', 'Pocas plazas, mucho mimo')) . '</h2><p>'
    . nl2br(e(setting('about_text', "Creemos en los planes pequeños y bien hechos. Trabajamos con chefs, artesanas y marcas que admiramos para crear encuentros en los que solo tienes que llegar y disfrutar.\n\nCada evento tiene plazas limitadas para que la experiencia sea cercana, cómoda y diferente."))) . '</p></div></section>';
$h .= '<section class="news"><div class="wrap narrow"><h2>Entérate antes que nadie</h2><p>Las plazas vuelan. Te avisamos de cada nueva fecha, sin llenarte el buzón.</p>'
    . '<form method="post" action="' . e(url('suscribir')) . '" class="newsform">' . csrf_field() . '<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off"><input type="email" name="email" required placeholder="Tu correo electrónico" aria-label="Tu correo electrónico"><button class="cta">Quiero enterarme</button>'
    . '<label class="consent"><input type="checkbox" name="ok" value="1" required> <span>Acepto recibir novedades y la <a href="' . e(url('legal/privacidad')) . '">política de privacidad</a>.</span></label></form></div></section>';
site_page('', $h);
