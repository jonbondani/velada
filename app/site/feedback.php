<?php
$o = row("SELECT * FROM orders WHERE token=? AND status='paid'", [$seg[1] ?? '']);
if (!$o) not_found();
$c = order_ctx($o['id']); $done = row('SELECT * FROM feedback WHERE order_id=?', [$o['id']]);
if (is_post()) {
    $d = ['event_id' => $o['event_id'], 'order_id' => $o['id'], 'rating' => max(1, min(5, (int)($_POST['rating'] ?? 0))), 'nps' => max(0, min(10, (int)($_POST['nps'] ?? 0))),
        'liked' => mb_substr(trim((string)($_POST['liked'] ?? '')), 0, 2000), 'improve' => mb_substr(trim((string)($_POST['improve'] ?? '')), 0, 2000)];
    if ((int)($_POST['rating'] ?? 0) < 1) { flash('Elige una valoración de 1 a 5 estrellas.', 'err'); redirect(url('opinion/' . $o['token'])); }
    if ($done) update('feedback', $d, $done['id']); else insert('feedback', $d + ['created_at' => now()]);
    redirect(url('opinion/' . $o['token'], ['ok' => 1]));
}
if (isset($_GET['ok'])) { site_page('Gracias', '<section class="wrap narrow center pad"><h1>Gracias, ' . e(first_name($c['cname'])) . '</h1><p class="lead">Tu opinión nos ayuda a preparar encuentros cada vez mejores.</p><p><a class="cta" href="' . e(url('')) . '#eventos">Ver próximos eventos</a></p></section>', ['noindex' => true]); return; }
$h = '<section class="wrap narrow order"><p class="eyebrow">' . e($c['title']) . '</p><h1>¿Qué te pareció?</h1><p class="lead">Es menos de un minuto y nos ayuda muchísimo.</p>'
    . '<form method="post" class="fbform">' . csrf_field() . '<fieldset><legend>Tu valoración general</legend><div class="rate">';
for ($i = 5; $i >= 1; $i--) $h .= '<input type="radio" name="rating" id="r' . $i . '" value="' . $i . '"' . (($done['rating'] ?? 0) == $i ? ' checked' : '') . ' required><label for="r' . $i . '" title="' . $i . ' de 5">★</label>';
$h .= '</div></fieldset><fieldset><legend>¿Lo recomendarías a una amiga?</legend><div class="nps">';
for ($i = 0; $i <= 10; $i++) $h .= '<label><input type="radio" name="nps" value="' . $i . '"' . (($done['nps'] ?? 8) == $i ? ' checked' : '') . '><span>' . $i . '</span></label>';
$h .= '</div><div class="npsl"><span>Nada probable</span><span>Segurísimo</span></div></fieldset>'
    . '<label><span>Lo que más te gustó</span><textarea name="liked" rows="3">' . e($done['liked'] ?? '') . '</textarea></label><label><span>Lo que mejorarías</span><textarea name="improve" rows="3">' . e($done['improve'] ?? '') . '</textarea></label>'
    . '<button class="cta wide">Enviar mi opinión</button></form></section>';
site_page('Tu opinión', $h, ['noindex' => true]);
