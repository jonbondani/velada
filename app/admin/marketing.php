<?php
// Marketing: calendario de publicaciones, cuentas de redes, metricas, codigos promocionales y suscriptoras
need('marketing');
$w = can('marketing', 'w');
$tabsAll = ['calendario' => 'Publicaciones', 'cuentas' => 'Cuentas', 'metricas' => 'Seguidores y alcance', 'codigos' => 'Códigos promocionales', 'suscriptoras' => 'Suscriptoras'];
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'calendario';
$P = ['tab' => $tab]; $self = fn($x = []) => url('admin/marketing', $x + $P);
$accOpts = function () { $o = []; foreach (rows('SELECT * FROM social_accounts ORDER BY active DESC, platform') as $a) $o[$a['id']] = (PLATFORMS[$a['platform']] ?? $a['platform']) . ($a['handle'] ? ' ' . $a['handle'] : ''); return $o; };

// Lectura de datos desde Instagram (cuenta profesional con token de la API de Meta)
function ig_get($path, array $params) {
    $ch = curl_init('https://graph.facebook.com/' . $path . '?' . http_build_query($params));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    if (!is_array($j)) throw new RuntimeException('Instagram no responde.');
    if (isset($j['error'])) throw new RuntimeException('Instagram ha rechazado la consulta: ' . ($j['error']['message'] ?? ''));
    return $j;
}
function ig_sync(array $a) {
    $me = ig_get($a['ext_id'], ['fields' => 'followers_count,media_count', 'access_token' => $a['access_token']]);
    $m = row('SELECT id FROM social_metrics WHERE account_id=? AND day=?', [$a['id'], date('Y-m-d')]);
    if ($m) update('social_metrics', ['followers' => (int)($me['followers_count'] ?? 0)], $m['id']);
    else insert('social_metrics', ['account_id' => $a['id'], 'day' => date('Y-m-d'), 'followers' => (int)($me['followers_count'] ?? 0)]);
    $media = ig_get($a['ext_id'] . '/media', ['fields' => 'id,caption,media_type,permalink,timestamp,like_count,comments_count', 'limit' => 25, 'access_token' => $a['access_token']]);
    $n = 0;
    foreach ($media['data'] ?? [] as $p) {
        $d = ['likes' => (int)($p['like_count'] ?? 0), 'comments' => (int)($p['comments_count'] ?? 0), 'url' => (string)($p['permalink'] ?? ''), 'ext_id' => (string)$p['id']];
        $ex = row('SELECT id FROM social_posts WHERE account_id=? AND (ext_id=? OR (url<>? AND url=?))', [$a['id'], $d['ext_id'], '', $d['url']]);
        if ($ex) update('social_posts', $d, $ex['id']);
        else insert('social_posts', $d + ['account_id' => $a['id'], 'title' => mb_strimwidth(trim((string)($p['caption'] ?? 'Publicación')), 0, 80, '…'), 'body' => (string)($p['caption'] ?? ''), 'status' => 'publicado',
            'format' => ['VIDEO' => 'Reel', 'CAROUSEL_ALBUM' => 'Carrusel'][$p['media_type'] ?? ''] ?? 'Publicación', 'scheduled_at' => date('Y-m-d H:i:s', strtotime((string)($p['timestamp'] ?? 'now')))]);
        $n++;
    }
    return $n;
}
if (is_post() && ($_GET['act'] ?? '') === 'sync') {
    need('marketing', 'w');
    foreach (rows("SELECT * FROM social_accounts WHERE platform='instagram' AND active=1 AND ext_id<>'' AND access_token IS NOT NULL AND access_token<>''") as $a) {
        try { $n = ig_sync($a); flash('Instagram ' . $a['handle'] . ' actualizado: seguidores de hoy y ' . $n . ' publicaciones.'); } catch (Throwable $e) { flash($e->getMessage(), 'err'); }
    }
    redirect($self());
}

$h = ''; $actions = ''; $sub = null;
switch ($tab) {
case 'calendario':
    $sub = crud(['table' => 'social_posts', 'route' => 'admin/marketing', 'params' => $P, 'perm' => 'marketing', 'singular' => 'publicación', 'new' => 'Planificar publicación', 'order' => "CASE WHEN status='publicado' THEN 1 ELSE 0 END, scheduled_at IS NULL, scheduled_at", 'search' => ['title', 'body'], 'filters' => ['status', 'account_id'],
        'empty' => 'Planifica aquí qué vas a publicar en cada red, para qué evento y cuándo. Después anota los resultados para saber qué funciona.',
        'fields' => [
            'title' => ['label' => 'Título o idea', 'req' => 1, 'list' => 1, 'short' => 'Publicación', 'full' => 1],
            'account_id' => ['label' => 'Cuenta', 'type' => 'select', 'req' => 1, 'options' => $accOpts, 'list' => 1, 'short' => 'Red'],
            'event_id' => ['label' => 'Evento que promociona', 'type' => 'select', 'options' => fn() => pairs('SELECT id, title FROM events ORDER BY starts_at DESC'), 'empty' => 'Ninguno, es contenido de marca', 'default' => (int)($_GET['ev'] ?? 0) ?: '', 'list' => 1, 'short' => 'Evento'],
            'format' => ['label' => 'Formato', 'type' => 'select', 'options' => fn() => opts(POST_FORMATS), 'default' => 'Publicación', 'list' => 1],
            'status' => ['label' => 'Estado', 'type' => 'select', 'req' => 1, 'options' => POST_STATUS, 'default' => 'idea', 'list' => 1, 'badge' => POST_BADGE],
            'scheduled_at' => ['label' => 'Fecha de publicación', 'type' => 'datetime', 'list' => 1, 'short' => 'Fecha'],
            'body' => ['label' => 'Texto de la publicación', 'type' => 'textarea', 'rows' => 6],
            'url' => ['label' => 'Enlace a la publicación (cuando esté publicada)', 'type' => 'url', 'full' => 1],
            'utm_campaign' => ['label' => 'Nombre de campaña', 'help' => 'Para relacionarla con las ventas que llegan con ese nombre en el enlace (utm_campaign).'],
            'spend' => ['label' => 'Inversión en publicidad', 'type' => 'money'],
            'reach' => ['label' => 'Alcance (cuentas alcanzadas)', 'type' => 'number', 'section' => 'Resultados', 'list' => 1, 'short' => 'Alcance'],
            'impressions' => ['label' => 'Impresiones o reproducciones', 'type' => 'number'],
            'likes' => ['label' => 'Me gusta', 'type' => 'number'],
            'comments' => ['label' => 'Comentarios', 'type' => 'number'],
            'shares' => ['label' => 'Compartidos', 'type' => 'number'],
            'saves' => ['label' => 'Guardados', 'type' => 'number'],
            'clicks' => ['label' => 'Clics en el enlace', 'type' => 'number'],
            'notes' => ['label' => 'Notas', 'type' => 'textarea', 'rows' => 2],
        ],
        'cols' => ['Interacciones' => fn($r) => '<span class="n">' . num($r['likes'] + $r['comments'] + $r['shares'] + $r['saves']) . '</span>'],
    ]);
    if ($sub['mode'] === 'list' && !val('SELECT COUNT(*) FROM social_accounts')) $sub['html'] = '<div class="flash">Antes de planificar, añade tus cuentas en la pestaña Cuentas.</div>' . $sub['html'];
    break;
case 'cuentas':
    $sub = crud(['table' => 'social_accounts', 'route' => 'admin/marketing', 'params' => $P, 'perm' => 'marketing', 'singular' => 'cuenta', 'new' => 'Añadir cuenta', 'order' => 'active DESC, platform',
        'intro' => 'Todas las redes y canales donde promocionas los eventos. Instagram es la principal, pero puedes llevar aquí cualquier otra.',
        'fields' => [
            'platform' => ['label' => 'Red o canal', 'type' => 'select', 'req' => 1, 'options' => PLATFORMS, 'default' => 'instagram', 'list' => 1],
            'handle' => ['label' => 'Nombre de la cuenta', 'ph' => '@tumarca', 'list' => 1],
            'url' => ['label' => 'Enlace al perfil', 'type' => 'url', 'full' => 1],
            'active' => ['label' => 'Cuenta en uso', 'type' => 'check', 'default' => 1, 'list' => 1, 'short' => 'En uso'],
            'ext_id' => ['label' => 'ID de la cuenta profesional de Instagram', 'section' => 'Conexión automática con Instagram (opcional)', 'help' => 'Con el ID y el token, el botón "Actualizar desde Instagram" trae seguidores y resultados sin teclearlos.'],
            'access_token' => ['label' => 'Token de acceso de la API de Meta', 'type' => 'password'],
        ],
        'cols' => ['Seguidores' => function ($r) { $f = val('SELECT followers FROM social_metrics WHERE account_id=? ORDER BY day DESC', [$r['id']]); return $f === null ? '<span class="mute">Sin datos</span>' : '<span class="n">' . num($f) . '</span>'; }],
        'validate' => function (array &$d, $rec) { if ($d['access_token'] === '' && $rec) unset($d['access_token']); return null; },
        'before_delete' => function ($r) { if (val('SELECT COUNT(*) FROM social_posts WHERE account_id=?', [$r['id']])) throw new RuntimeException('Esta cuenta tiene publicaciones. Desmarca "Cuenta en uso" en lugar de eliminarla.'); q('DELETE FROM social_metrics WHERE account_id=?', [$r['id']]); },
    ]);
    break;
case 'metricas':
    $sub = crud(['table' => 'social_metrics', 'route' => 'admin/marketing', 'params' => $P, 'perm' => 'marketing', 'singular' => 'registro', 'new' => 'Anotar datos', 'order' => 'day DESC, id DESC', 'filters' => ['account_id'],
        'intro' => 'Anota cada semana o cada mes los datos de cada cuenta para ver su evolución en Estadísticas. Con Instagram conectado se rellena solo.',
        'fields' => [
            'account_id' => ['label' => 'Cuenta', 'type' => 'select', 'req' => 1, 'options' => $accOpts, 'list' => 1],
            'day' => ['label' => 'Fecha', 'type' => 'date', 'req' => 1, 'default' => date('Y-m-d'), 'list' => 1],
            'followers' => ['label' => 'Seguidores', 'type' => 'number', 'req' => 1, 'list' => 1],
            'reach' => ['label' => 'Alcance del periodo', 'type' => 'number', 'list' => 1, 'short' => 'Alcance'],
            'impressions' => ['label' => 'Impresiones del periodo', 'type' => 'number'],
            'profile_visits' => ['label' => 'Visitas al perfil', 'type' => 'number', 'list' => 1],
            'link_clicks' => ['label' => 'Clics en el enlace del perfil', 'type' => 'number', 'list' => 1, 'short' => 'Clics al enlace'],
        ],
    ]);
    if ($w && $sub['mode'] === 'list' && val("SELECT COUNT(*) FROM social_accounts WHERE platform='instagram' AND ext_id<>'' AND access_token IS NOT NULL AND access_token<>''")) $sub['actions'] = post_btn('Actualizar desde Instagram', $self(['act' => 'sync'])) . $sub['actions'];
    break;
case 'codigos':
    $sub = crud(['table' => 'discount_codes', 'route' => 'admin/marketing', 'params' => $P, 'perm' => 'marketing', 'singular' => 'código promocional', 'new' => 'Crear código', 'order' => 'active DESC, id DESC',
        'intro' => 'Códigos de descuento para campañas, colaboraciones con creadoras de contenido o clientas habituales. Cada reserva guarda qué código se usó.',
        'fields' => [
            'code' => ['label' => 'Código', 'req' => 1, 'list' => 1, 'ph' => 'AMIGAS10', 'help' => 'Sin espacios. Se guarda en mayúsculas.'],
            'kind' => ['label' => 'Tipo de descuento', 'type' => 'select', 'req' => 1, 'options' => ['pct' => 'Porcentaje', 'fixed' => 'Importe fijo en euros'], 'default' => 'pct', 'list' => 1, 'short' => 'Tipo'],
            'amount' => ['label' => 'Valor', 'type' => 'money', 'req' => 1, 'help' => 'Por ejemplo 10 para un 10 % o para 10 €.'],
            'event_id' => ['label' => 'Válido para', 'type' => 'select', 'options' => fn() => pairs("SELECT id, title FROM events WHERE status IN ('draft','published') ORDER BY starts_at"), 'empty' => 'Todos los eventos', 'list' => 1],
            'max_uses' => ['label' => 'Usos máximos', 'type' => 'number', 'help' => '0 = sin límite.'],
            'valid_until' => ['label' => 'Válido hasta', 'type' => 'date', 'list' => 1],
            'active' => ['label' => 'Código activo', 'type' => 'check', 'default' => 1, 'list' => 1, 'short' => 'Activo'],
        ],
        'cols' => ['Valor' => fn($r) => '<span class="n">' . ($r['kind'] === 'pct' ? num($r['amount'], 0) . ' %' : money($r['amount'])) . '</span>', 'Usos' => fn($r) => '<span class="n">' . (int)$r['used'] . ((int)$r['max_uses'] ? ' / ' . (int)$r['max_uses'] : '') . '</span>'],
        'validate' => function (array &$d, $rec) { $d['code'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $d['code'])); if ($d['code'] === '') return 'El código solo puede llevar letras y números.'; if ($d['kind'] === 'pct' && (float)$d['amount'] > 100) return 'Un porcentaje no puede superar el 100 %.'; return null; },
    ]);
    break;
case 'suscriptoras':
    $list = rows('SELECT * FROM customers WHERE marketing_ok=1 ORDER BY id DESC LIMIT 1000');
    if (($_GET['do'] ?? '') === 'csv') csv_out('suscriptoras', ['Nombre', 'Correo', 'Teléfono', 'Origen', 'Alta'], array_map(fn($r) => [$r['name'], $r['email'], $r['phone'], $r['source'], fdate($r['created_at'])], $list));
    $h .= '<p class="intro">Personas que han aceptado recibir novedades, al reservar o desde el formulario de la web. Descarga la lista para tu herramienta de envío de correos.</p>';
    if (!$list) { $h .= '<div class="empty"><b>Todavía no hay suscriptoras.</b><br>Se añaden solas cuando alguien marca que quiere recibir novedades.</div>'; break; }
    $actions = btn('Descargar lista', $self(['do' => 'csv']));
    $h .= '<div class="card table"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Origen</th><th>Alta</th></tr></thead><tbody>';
    foreach ($list as $r) $h .= '<tr><td><a class="lead" href="' . e(url('admin/customers', ['do' => 'edit', 'rid' => $r['id']])) . '">' . e($r['name'] ?: '(sin nombre)') . '</a></td><td>' . e($r['email']) . '</td><td>' . e(['newsletter' => 'Formulario de la web', 'compra' => 'Al reservar', 'taquilla' => 'Venta manual', 'manual' => 'Añadida a mano'][$r['source']] ?? $r['source']) . '</td><td>' . fdate($r['created_at']) . '</td></tr>';
    $h .= '</tbody></table></div>';
    break;
}
$back = '';
if ($sub) { $h .= $sub['html']; $actions = $sub['mode'] === 'list' ? $sub['actions'] : ''; $back = $sub['mode'] === 'form' ? $self() : ''; }
admin_page('Marketing y redes', ($back ? '' : tabs('admin/marketing', [], $tabsAll, $tab)) . $h, $actions, $back);
