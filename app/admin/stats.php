<?php
// Estadisticas: vision general, web, redes sociales, comparativa de eventos y opiniones
need('stats');
$tabsAll = ['general' => 'Visión general', 'web' => 'Web', 'redes' => 'Redes sociales', 'eventos' => 'Eventos', 'opiniones' => 'Opiniones'];
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';
$d = (int)($_GET['d'] ?? 90); if (!in_array($d, [30, 90, 365], true)) $d = 90;
$from = date('Y-m-d', time() - ($d - 1) * 86400); $fromDt = $from . ' 00:00:00';

// Agrupa valores por dia, semana o mes segun el periodo elegido
$bucket = function (array $rowsDayVal, $agg = 'sum') use ($d, $from) {
    $out = []; $cnt = [];
    $key = function ($day) use ($d) { $t = strtotime($day); if ($d <= 30) return date('d/m', $t); if ($d <= 90) return date('d/m', strtotime('monday this week', $t)); return mb_substr(MESES[(int)date('n', $t)], 0, 3) . ' ' . date('y', $t); };
    for ($t = strtotime($from); $t <= time(); $t += 86400) $out[$key(date('Y-m-d', $t))] = 0;
    foreach ($rowsDayVal as $r) { $k = $key($r['d']); if (!isset($out[$k])) continue; if ($agg === 'last') $out[$k] = (float)$r['v']; else $out[$k] += (float)$r['v']; }
    return $out;
};
$period = '<div class="seg">';
foreach ([30 => '30 días', 90 => '3 meses', 365 => '12 meses'] as $k => $l) $period .= '<a href="' . e(url('admin/stats', ['tab' => $tab, 'd' => $k])) . '"' . ($k === $d ? ' class="on"' : '') . '>' . $l . '</a>';
$period .= '</div>';
$h = '';

switch ($tab) {
case 'general':
    $o = row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE status='paid' AND paid_at>=?", [$fromDt]);
    $tix = (int)val("SELECT COALESCE(SUM(oi.qty*oi.seats),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status='paid' AND o.paid_at>=?", [$fromDt]);
    $visitors = (int)val("SELECT COUNT(DISTINCT visitor) FROM pageviews WHERE kind='view' AND day>=?", [$from]);
    $webOrders = (int)val("SELECT COUNT(*) FROM orders WHERE status='paid' AND created_by IS NULL AND paid_at>=?", [$fromDt]);
    $fb = row('SELECT COUNT(*) AS n, AVG(rating) AS a FROM feedback WHERE created_at>=?', [$fromDt]);
    $h .= '<div class="kpis">' . kpi('Ingresos', money($o['t']), (int)$o['n'] . ' reservas pagadas') . kpi('Entradas vendidas', num($tix)) . kpi('Importe medio por reserva', $o['n'] ? money($o['t'] / $o['n']) : '<span class="mute">Sin datos</span>')
        . kpi('Visitantes de la web', num($visitors), $visitors ? num(100 * $webOrders / $visitors, 1) . ' % acaban reservando' : '') . kpi('Valoración media', $fb['n'] ? num($fb['a'], 1) . '<i> / 5</i>' : '<span class="mute">Sin datos</span>', $fb['n'] ? (int)$fb['n'] . ' opiniones' : '') . '</div>';
    $h .= panel('Evolución de los ingresos', chart_bars($bucket(rows("SELECT SUBSTR(paid_at,1,10) AS d, SUM(total) AS v FROM orders WHERE status='paid' AND paid_at>=? GROUP BY SUBSTR(paid_at,1,10)", [$fromDt])), 'money'));
    $byEv = []; foreach (rows("SELECT e.title, SUM(o.total) AS t FROM orders o JOIN events e ON e.id=o.event_id WHERE o.status='paid' AND o.paid_at>=? GROUP BY e.id, e.title", [$fromDt]) as $r) $byEv[$r['title']] = (float)$r['t'];
    $byCh = []; foreach (rows("SELECT utm_source, created_by, SUM(total) AS t FROM orders WHERE status='paid' AND paid_at>=? GROUP BY utm_source, created_by", [$fromDt]) as $r) { $k = $r['created_by'] ? 'Venta manual' : channel_label($r['utm_source']); $byCh[$k] = ($byCh[$k] ?? 0) + (float)$r['t']; }
    $h .= '<div class="cols">' . panel('Eventos con más ingresos', hbars($byEv, 'money')) . panel('De dónde vienen las ventas', hbars($byCh, 'money')) . '</div>';
    break;

case 'web':
    $views = (int)val("SELECT COUNT(*) FROM pageviews WHERE kind='view' AND day>=?", [$from]);
    $visitors = (int)val("SELECT COUNT(DISTINCT visitor) FROM pageviews WHERE kind='view' AND day>=?", [$from]);
    $evVisitors = (int)val("SELECT COUNT(DISTINCT visitor) FROM pageviews WHERE kind='view' AND event_id IS NOT NULL AND day>=?", [$from]);
    $started = (int)val('SELECT COUNT(*) FROM orders WHERE created_by IS NULL AND created_at>=?', [$fromDt]);
    $paid = (int)val("SELECT COUNT(*) FROM orders WHERE created_by IS NULL AND status='paid' AND created_at>=?", [$fromDt]);
    $h .= '<div class="kpis">' . kpi('Páginas vistas', num($views)) . kpi('Visitantes', num($visitors)) . kpi('Reservas iniciadas', num($started)) . kpi('Reservas pagadas', num($paid), $visitors ? num(100 * $paid / $visitors, 1) . ' % de los visitantes' : '') . '</div>';
    $h .= panel('Visitas a la web', chart_line($bucket(rows("SELECT day AS d, COUNT(*) AS v FROM pageviews WHERE kind='view' AND day>=? GROUP BY day", [$from]))));
    $fun = ['Visitan la web' => $visitors, 'Miran un evento' => $evVisitors, 'Inician la reserva' => $started, 'Pagan' => $paid]; $fh = '<div class="funnel">'; $top = max(1, $visitors);
    foreach ($fun as $l => $v) $fh .= '<div><span>' . $l . '</span><i><b style="width:' . max(2, round(100 * $v / $top)) . '%"></b></i><em>' . num($v) . '</em></div>';
    $src = []; foreach (rows("SELECT utm_source, referrer, COUNT(DISTINCT visitor) AS n FROM pageviews WHERE kind='view' AND day>=? GROUP BY utm_source, referrer", [$from]) as $r) { $k = channel_label($r['utm_source'], $r['referrer']); $src[$k] = ($src[$k] ?? 0) + (int)$r['n']; }
    $dev = []; foreach (rows("SELECT device, COUNT(DISTINCT visitor) AS n FROM pageviews WHERE kind='view' AND day>=? AND device<>'' GROUP BY device", [$from]) as $r) $dev[$r['device']] = (int)$r['n'];
    $pg = []; foreach (rows("SELECT e.title, COUNT(*) AS n FROM pageviews p JOIN events e ON e.id=p.event_id WHERE p.kind='view' AND p.day>=? GROUP BY e.id, e.title", [$from]) as $r) $pg[$r['title']] = (int)$r['n'];
    $h .= '<div class="cols">' . panel('Del primer vistazo a la reserva', $fh . '</div>') . panel('De dónde llegan las visitas', hbars($src)) . '</div><div class="cols">' . panel('Eventos más vistos', hbars($pg)) . panel('Desde qué dispositivo', hbars($dev)) . '</div>';
    $h .= '<p class="mute small">Las visitas se cuentan en la propia plataforma, sin cookies ni servicios externos. No se cuenta al equipo cuando navega con la sesión iniciada.</p>';
    break;

case 'redes':
    $accs = rows('SELECT * FROM social_accounts WHERE active=1 ORDER BY platform');
    if (!$accs) { $h .= '<div class="empty"><b>Aún no hay cuentas de redes dadas de alta.</b><br>Añádelas en Marketing y redes para empezar a medir.<p>' . btn('Ir a Marketing', url('admin/marketing', ['tab' => 'cuentas']), 'primary') . '</p></div>'; break; }
    $ps = row("SELECT COUNT(*) AS n, COALESCE(SUM(reach),0) AS reach, COALESCE(SUM(likes+comments+shares+saves),0) AS eng, COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(spend),0) AS spend FROM social_posts WHERE status='publicado' AND scheduled_at>=?", [$fromDt]);
    $socialSales = 0; foreach (rows("SELECT utm_source, SUM(total) AS t FROM orders WHERE status='paid' AND paid_at>=? AND utm_source<>'' GROUP BY utm_source", [$fromDt]) as $r) if (isset(PLATFORMS[strtolower($r['utm_source'])]) || in_array(channel_label($r['utm_source']), PLATFORMS, true)) $socialSales += (float)$r['t'];
    $h .= '<div class="kpis">' . kpi('Publicaciones', num($ps['n'])) . kpi('Alcance total', num($ps['reach'])) . kpi('Interacciones', num($ps['eng']), $ps['reach'] > 0 ? num(100 * $ps['eng'] / $ps['reach'], 1) . ' % del alcance' : '')
        . kpi('Clics hacia la web', num($ps['clicks'])) . kpi('Ventas llegadas desde redes', money($socialSales), (float)$ps['spend'] > 0 ? money($ps['spend']) . ' invertidos en anuncios' : '') . '</div>';
    $ac = '';
    foreach ($accs as $a) {
        $ms = rows('SELECT day AS d, followers AS v FROM social_metrics WHERE account_id=? AND day>=? ORDER BY day', [$a['id'], $from]);
        $last = val('SELECT followers FROM social_metrics WHERE account_id=? ORDER BY day DESC', [$a['id']]);
        $first = $ms ? (int)$ms[0]['v'] : null; $grow = $first !== null && $last !== null ? (int)$last - $first : null;
        $series = []; foreach ($ms as $m) $series[fdate($m['d'], 'd/m')] = (int)$m['v'];
        $ac .= panel((PLATFORMS[$a['platform']] ?? $a['platform']) . ' ' . $a['handle'], '<div class="kpis in">' . kpi('Seguidores', $last === null ? '<span class="mute">Sin datos</span>' : num($last), $grow === null ? '' : '<span class="' . ($grow >= 0 ? 'pos' : 'neg') . '">' . ($grow >= 0 ? '+' : '') . num($grow) . ' en el periodo</span>') . '</div>' . chart_line($series));
    }
    $h .= '<div class="cols">' . $ac . '</div>';
    $posts = rows("SELECT sp.*, a.platform FROM social_posts sp LEFT JOIN social_accounts a ON a.id=sp.account_id WHERE sp.status='publicado' AND sp.scheduled_at>=? ORDER BY (sp.likes+sp.comments+sp.shares+sp.saves) DESC LIMIT 10", [$fromDt]);
    $t = '<table class="plain"><thead><tr><th>Publicación</th><th>Red</th><th>Formato</th><th class="r">Alcance</th><th class="r">Interacciones</th><th class="r">Clics</th></tr></thead><tbody>';
    foreach ($posts as $r) $t .= '<tr><td><a href="' . e(url('admin/marketing', ['tab' => 'calendario', 'do' => 'edit', 'rid' => $r['id']])) . '">' . e($r['title']) . '</a></td><td>' . e(PLATFORMS[$r['platform']] ?? '') . '</td><td>' . e($r['format']) . '</td><td class="r">' . num($r['reach']) . '</td><td class="r">' . num($r['likes'] + $r['comments'] + $r['shares'] + $r['saves']) . '</td><td class="r">' . num($r['clicks']) . '</td></tr>';
    $fmt = []; foreach (rows("SELECT format, AVG(likes+comments+shares+saves) AS v FROM social_posts WHERE status='publicado' AND scheduled_at>=? AND format<>'' GROUP BY format", [$fromDt]) as $r) $fmt[$r['format']] = round((float)$r['v']);
    $h .= '<div class="cols wide-l">' . panel('Publicaciones que mejor han funcionado', $posts ? $t . '</tbody></table>' : '<div class="nochart">Aún no hay publicaciones con resultados en este periodo.</div>') . panel('Interacciones medias por formato', hbars($fmt)) . '</div>';
    break;

case 'eventos':
    $evs = rows("SELECT e.*, v.name AS vname FROM events e LEFT JOIN venues v ON v.id=e.venue_id WHERE e.status<>'draft' AND e.starts_at>=? ORDER BY e.starts_at DESC", [$fromDt]);
    if (!$evs) { $h .= '<div class="empty"><b>No hay eventos en este periodo.</b></div>'; break; }
    $t = '<div class="card table"><table><thead><tr><th>Evento</th><th>Fecha</th><th class="r">Vendidas</th><th>Ocupación</th><th class="r">Ingresos</th><th class="r">Asistencia</th><th>Valoración</th></tr></thead><tbody>';
    $byDow = []; $byCat = [];
    foreach ($evs as $ev) {
        $s = event_stats($ev['id']); $cap = (int)$ev['capacity']; $occ = $cap ? 100 * $s['sold'] / $cap : null;
        $fb = row('SELECT COUNT(*) AS n, AVG(rating) AS a FROM feedback WHERE event_id=?', [$ev['id']]);
        $t .= '<tr data-href="' . e(url('admin/event', ['id' => $ev['id']])) . '"><td><a class="lead" href="' . e(url('admin/event', ['id' => $ev['id']])) . '">' . e($ev['title']) . '</a></td><td>' . fdate($ev['starts_at']) . '</td><td class="r">' . $s['sold'] . ($cap ? ' / ' . $cap : '') . '</td><td style="min-width:110px">' . ($occ === null ? '' : progress($occ, $occ >= 90 ? 'green' : '') . '<small class="mute">' . num($occ, 0) . ' %</small>') . '</td><td class="r"><span class="n">' . money($s['revenue']) . '</span></td><td class="r">' . ($s['sold'] && $ev['starts_at'] < now() ? num(100 * $s['checked'] / $s['sold'], 0) . ' %' : '') . '</td><td>' . ($fb['n'] ? stars($fb['a']) . ' <small class="mute">' . num($fb['a'], 1) . '</small>' : '<span class="mute">Sin opiniones</span>') . '</td></tr>';
        if ($occ !== null) { $k = ucfirst(DIAS[(int)date('w', strtotime($ev['starts_at']))]); $byDow[$k][] = $occ; if ($ev['category']) $byCat[$ev['category']][] = $occ; }
    }
    $avg = fn($a) => array_map(fn($v) => round(array_sum($v) / count($v)), $a);
    $h .= $t . '</tbody></table></div><div class="cols">' . panel('Ocupación media según el día de la semana', hbars($avg($byDow), fn($v) => num($v) . ' %')) . panel('Ocupación media según el tipo de evento', hbars($avg($byCat), fn($v) => num($v) . ' %')) . '</div>';
    break;

case 'opiniones':
    $fb = rows('SELECT f.*, e.title, c.name FROM feedback f JOIN events e ON e.id=f.event_id LEFT JOIN orders o ON o.id=f.order_id LEFT JOIN customers c ON c.id=o.customer_id WHERE f.created_at>=? ORDER BY f.created_at DESC', [$fromDt]);
    if (!$fb) { $h .= '<div class="empty"><b>No hay opiniones en este periodo.</b><br>Envía la encuesta desde la ficha de cada evento cuando termine.</div>'; break; }
    $n = count($fb); $avg = array_sum(array_column($fb, 'rating')) / $n;
    $pro = count(array_filter($fb, fn($f) => $f['nps'] >= 9)); $det = count(array_filter($fb, fn($f) => $f['nps'] <= 6)); $nps = round(100 * ($pro - $det) / $n);
    $h .= '<div class="kpis">' . kpi('Valoración media', num($avg, 1) . '<i> / 5</i>', stars($avg)) . kpi('Recomendación (NPS)', ($nps > 0 ? '+' : '') . $nps, 'De -100 a +100. Por encima de +50 es excelente.') . kpi('Opiniones recibidas', $n) . '</div>';
    $dist = []; for ($i = 5; $i >= 1; $i--) $dist[$i . ' ' . ($i === 1 ? 'estrella' : 'estrellas')] = count(array_filter($fb, fn($f) => (int)$f['rating'] === $i));
    $byEv = []; foreach ($fb as $f) $byEv[$f['title']][] = (int)$f['rating'];
    $distH = '<div class="hbars">'; $mx = max(1, max($dist)); foreach ($dist as $l => $v) $distH .= '<div><span>' . $l . '</span><i><b style="width:' . round(100 * $v / $mx, 1) . '%"></b></i><em>' . $v . '</em></div>'; $distH .= '</div>';
    $h .= '<div class="cols">' . panel('Reparto de valoraciones', $distH) . panel('Valoración media por evento', hbars(array_map(fn($v) => round(array_sum($v) / count($v), 1), $byEv), fn($v) => num($v, 1))) . '</div>';
    $c = '';
    foreach ($fb as $f) if (trim((string)$f['liked']) !== '' || trim((string)$f['improve']) !== '')
        $c .= '<div class="quote">' . stars($f['rating']) . ' <b>' . e($f['name'] ?: 'Anónimo') . '</b> <span class="mute">' . e($f['title']) . '</span>' . ($f['liked'] ? '<p>' . e($f['liked']) . '</p>' : '') . ($f['improve'] ? '<p class="mute">A mejorar: ' . e($f['improve']) . '</p>' : '') . '</div>';
    $h .= panel('Lo que dicen', $c ?: '<div class="nochart">Nadie ha dejado comentarios escritos.</div>');
    break;
}
admin_page('Estadísticas', tabs('admin/stats', ['d' => $d], $tabsAll, $tab) . $h, $period);
