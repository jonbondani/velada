<?php
// Acceso de IA: conexiones (tokens) de cada usuario y, solo para el superusuario, interruptor general y registro completo
$u = user(); $super = is_super();
if (!$super && !ai_allowed($u)) { http_response_code(403); admin_page('Sin acceso', '<div class="empty"><b>Tu perfil no tiene permiso para conectar una IA.</b><br>Si lo necesitas, pídeselo a quien administra la plataforma.</div>'); return; }
require_once ROOT . '/app/mcp.php';
$tabsAll = $super ? ['estado' => 'Estado y apagado', 'conexiones' => 'Conexiones', 'registro' => 'Registro'] : ['conexiones' => 'Mis conexiones'];
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : array_key_first($tabsAll);
$self = fn($x = []) => url('admin/ai', $x + ['tab' => $tab]);
$endpoint = abs_url('mcp');
$ST = ['ok' => ['Correcto', 'green'], 'denied' => ['Denegado por permisos', 'red'], 'auth' => ['Token rechazado', 'red'], 'off' => ['IA apagada', 'gray'], 'error' => ['Error', 'orange'], 'limit' => ['Límite superado', 'orange'], 'blocked' => ['Bloqueado', 'red'], 'switch' => ['Cambio en el panel', 'blue']];
$sysLog = fn($status, $detail) => insert('ai_log', ['created_at' => now(), 'user_id' => $u['id'], 'role' => $u['role'], 'ip' => client_ip(), 'client' => 'Panel', 'method' => 'panel', 'status' => $status, 'detail' => $detail]);

if (is_post()) {
    $act = $_GET['act'] ?? '';
    if ($act === 'switch' && $super) {
        $on = ($_POST['on'] ?? '') === '1'; set_setting('ai_enabled', $on ? '1' : '0');
        $sysLog('switch', ($on ? 'Acceso de IA ENCENDIDO' : 'Acceso de IA APAGADO') . ' por ' . $u['name']); log_activity($on ? 'Acceso de IA encendido' : 'Acceso de IA apagado');
        flash($on ? 'Acceso de IA encendido.' : 'Acceso de IA apagado. Ninguna IA puede conectarse hasta que lo vuelvas a encender.');
    } elseif ($act === 'config' && $super) {
        $roles = array_values(array_intersect(array_keys(ROLES), array_keys((array)($_POST['roles'] ?? []))));
        set_setting('ai_roles', implode(',', $roles)); set_setting('ai_rate', (string)max(10, min(600, (int)($_POST['rate'] ?? 120))));
        $sysLog('switch', 'Perfiles con permiso de IA: ' . (implode(', ', $roles) ?: 'ninguno')); flash('Configuración guardada.');
    } elseif ($act === 'new') {
        $name = trim((string)($_POST['name'] ?? '')); $days = (int)($_POST['days'] ?? 90);
        if ($name === '') flash('Pon un nombre a la conexión para reconocerla después.', 'err');
        elseif ((int)val('SELECT COUNT(*) FROM ai_tokens WHERE user_id=? AND revoked=0', [$u['id']]) >= 10) flash('Tienes demasiadas conexiones activas. Revoca alguna antes de crear otra.', 'err');
        else {
            $tok = 'vel_' . bin2hex(random_bytes(24));
            insert('ai_tokens', ['user_id' => $u['id'], 'name' => $name, 'token_hash' => hash('sha256', $tok), 'prefix' => substr($tok, 0, 10), 'readonly' => isset($_POST['readonly']) ? 1 : 0,
                'expires_at' => in_array($days, [30, 90, 365], true) ? date('Y-m-d H:i:s', time() + $days * 86400) : null, 'revoked' => 0, 'created_at' => now()]);
            $sysLog('switch', 'Conexión creada: ' . $name . (isset($_POST['readonly']) ? ' (solo consulta)' : '')); log_activity('Conexión de IA creada', $name);
            $_SESSION['new_ai_token'] = $tok;
        }
    } elseif ($act === 'revoke') {
        $t = row('SELECT * FROM ai_tokens WHERE id=?', [(int)($_GET['t'] ?? 0)]);
        if ($t && ($super || (int)$t['user_id'] === (int)$u['id'])) { q('UPDATE ai_tokens SET revoked=1 WHERE id=?', [$t['id']]); $sysLog('switch', 'Conexión revocada: ' . $t['name']); log_activity('Conexión de IA revocada', $t['name']); flash('Conexión revocada. Ese token ya no sirve.'); }
    } elseif ($act === 'revokeall' && $super) {
        q('UPDATE ai_tokens SET revoked=1 WHERE revoked=0'); $sysLog('switch', 'Todas las conexiones revocadas por ' . $u['name']); flash('Todas las conexiones han sido revocadas.');
    }
    redirect(url('admin/ai', ['tab' => in_array($act, ['new', 'revoke', 'revokeall'], true) ? 'conexiones' : $tab]));
}

$h = ''; $actions = '';
if ($tab === 'estado') {
    $on = ai_on(); $d0 = date('Y-m-d 00:00:00');
    $h .= '<div class="card panel aiswitch ' . ($on ? 'on' : 'off') . '"><div><span class="aidot"></span><div><h2>El acceso de IA está ' . ($on ? 'encendido' : 'apagado') . '</h2><p class="mute">'
        . ($on ? 'Las IA conectadas pueden trabajar, cada una con los permisos de su usuario.' : 'Ninguna IA puede conectarse ni hacer nada, tenga el token que tenga. Los intentos quedan anotados en el registro.') . '</p></div></div>'
        . '<form method="post" action="' . e($self(['act' => 'switch'])) . '">' . csrf_field() . '<input type="hidden" name="on" value="' . ($on ? '0' : '1') . '"><button class="btn ' . ($on ? 'danger big' : 'primary') . '"' . ($on ? '' : ' data-confirm="¿Encender el acceso de IA? Las conexiones activas volverán a funcionar."') . '>' . ($on ? 'Apagar la IA ahora' : 'Encender') . '</button></form></div>';
    $h .= '<div class="kpis">' . kpi('Peticiones de hoy', num(val("SELECT COUNT(*) FROM ai_log WHERE created_at>=? AND method<>'panel'", [$d0])))
        . kpi('Rechazadas hoy', num(val("SELECT COUNT(*) FROM ai_log WHERE created_at>=? AND status IN ('denied','auth','blocked','off','limit')", [$d0])), 'por permisos, token o apagado')
        . kpi('Conexiones activas', num(val('SELECT COUNT(*) FROM ai_tokens WHERE revoked=0 AND (expires_at IS NULL OR expires_at>?)', [now()]))) . '</div>';
    $roles = ai_roles(); $cfg = '<form method="post" action="' . e($self(['act' => 'config'])) . '">' . csrf_field() . '<p class="mute">Solo las personas con estos perfiles pueden crear conexiones de IA. La IA de cada una verá y hará exactamente lo mismo que esa persona en el panel, nunca más.</p><div class="rolegrid">';
    foreach (ROLES as $rk => $rl) $cfg .= '<label class="chk"><input type="checkbox" name="roles[' . $rk . ']" value="1"' . (in_array($rk, $roles, true) ? ' checked' : '') . '><span>' . e($rl) . '</span></label>';
    $cfg .= '</div><label class="fld" style="max-width:260px;margin-top:14px"><span>Máximo de peticiones por minuto y conexión</span><input type="number" name="rate" min="10" max="600" value="' . (int)setting('ai_rate', 120) . '"></label><p><button class="btn primary">Guardar</button></p></form>';
    $miss = mcp_coverage();
    $cov = '<dl class="dl"><dt>Dirección del servidor</dt><dd><code>' . e($endpoint) . '</code> <button type="button" class="link" data-copy="' . e($endpoint) . '">Copiar</button></dd><dt>Protocolo</dt><dd>MCP por HTTP, versión ' . MCP_VERSIONS[0] . '</dd>'
        . '<dt>Recursos expuestos</dt><dd>' . count(mcp_resources()) . ' de ' . (count(schema()) - count(MCP_INTERNAL)) . ' tablas de datos</dd><dt>Cobertura</dt><dd>' . ($miss ? badge('Faltan por clasificar: ' . implode(', ', $miss), 'red') : badge('Todo clasificado', 'green')) . '</dd></dl>'
        . '<p class="mute small">El servidor se genera a partir de la estructura de datos de la plataforma. Si una versión nueva añade una tabla sin indicar qué permiso la protege, aparece aquí en rojo y no se expone hasta que se clasifique.</p>';
    $h .= '<div class="cols">' . panel('Quién puede conectar una IA', $cfg) . panel('Servidor', $cov) . '</div>';
}
if ($tab === 'conexiones') {
    if (!empty($_SESSION['new_ai_token'])) {
        $tok = $_SESSION['new_ai_token']; unset($_SESSION['new_ai_token']);
        $json = json_encode(['mcpServers' => ['eventos' => ['type' => 'http', 'url' => $endpoint, 'headers' => ['Authorization' => 'Bearer ' . $tok]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $cmd = 'claude mcp add --transport http eventos ' . $endpoint . ' --header "Authorization: Bearer ' . $tok . '"';
        $h .= '<div class="card panel newtoken"><h2>Conexión creada. Copia el token ahora</h2><p>Por seguridad no se vuelve a mostrar. Si lo pierdes, revoca la conexión y crea otra.</p>'
            . '<div class="tokenbox"><code id="newtok">' . e($tok) . '</code><button type="button" class="btn sm primary" data-copy="' . e($tok) . '">Copiar</button></div>'
            . '<details open><summary>Cómo conectarlo</summary><p class="mute">Cualquier IA compatible con MCP por HTTP sirve. Necesita dos datos: la dirección del servidor y la cabecera <code>Authorization: Bearer</code> con el token.</p>'
            . '<p><b>Claude Code</b></p><pre>' . e($cmd) . '</pre><p><b>Configuración en JSON</b> (Claude Code, Cursor y otros clientes)</p><pre>' . e($json) . '</pre></details></div>';
    }
    if (!ai_on()) $h .= '<div class="flash">El acceso de IA está apagado' . ($super ? '. Enciéndelo en "Estado y apagado" para que las conexiones funcionen.' : ' por administración. Puedes preparar la conexión, pero no funcionará hasta que lo enciendan.') . '</div>';
    $h .= '<form method="post" action="' . e($self(['act' => 'new'])) . '" class="card form">' . csrf_field() . '<h2>Conectar una IA</h2><p class="intro">La IA actuará en tu nombre con tu perfil (' . e(ROLES[$u['role']] ?? '') . '). Podrá ver y hacer lo mismo que tú en el panel, y nada más. Todo lo que haga queda registrado.</p><div class="grid">'
        . render_field('name', ['label' => 'Nombre de la conexión', 'req' => 1, 'ph' => 'Por ejemplo: Claude en mi portátil'], '')
        . render_field('days', ['label' => 'Caducidad', 'type' => 'select', 'req' => 1, 'options' => [30 => '30 días', 90 => '90 días', 365 => '1 año', 0 => 'Sin caducidad']], 90)
        . render_field('readonly', ['label' => 'Solo consulta: esta IA puede leer, pero no crear, cambiar ni eliminar nada', 'type' => 'check', 'full' => 1], 1)
        . '</div><div class="form-foot"><button class="btn primary">Crear conexión</button></div></form>';
    $list = rows('SELECT t.*, us.name AS uname, us.role AS urole, us.active AS uactive FROM ai_tokens t LEFT JOIN users us ON us.id=t.user_id ' . ($super ? '' : 'WHERE t.user_id=' . (int)$u['id']) . ' ORDER BY t.revoked, t.id DESC LIMIT 300');
    if ($list) {
        $h .= '<div class="card table"><table><thead><tr><th>Conexión</th>' . ($super ? '<th>Usuario</th>' : '') . '<th>Alcance</th><th>Último uso</th><th>Caduca</th><th>Estado</th><th></th></tr></thead><tbody>';
        foreach ($list as $t) {
            $dead = $t['revoked'] || ($t['expires_at'] && $t['expires_at'] < now()) || !$t['uactive'];
            $h .= '<tr><td><b>' . e($t['name']) . '</b><br><code>' . e($t['prefix']) . '…</code></td>' . ($super ? '<td>' . e($t['uname'] ?: 'Usuario eliminado') . '<br><span class="mute">' . e(ROLES[$t['urole']] ?? '') . '</span></td>' : '')
                . '<td>' . ($t['readonly'] ? badge('Solo consulta') : badge('Consulta y cambios', 'blue')) . '</td><td>' . ($t['last_used_at'] ? fdt($t['last_used_at']) : '<span class="mute">Nunca</span>') . '</td><td>' . ($t['expires_at'] ? fdate($t['expires_at']) : '<span class="mute">No caduca</span>') . '</td>'
                . '<td>' . ($t['revoked'] ? badge('Revocada', 'red') : ($dead ? badge(!$t['uactive'] ? 'Usuario inactivo' : 'Caducada', 'orange') : badge('Activa', 'green'))) . '</td><td class="r">' . ($t['revoked'] ? '' : post_btn('Revocar', $self(['act' => 'revoke', 't' => $t['id']]), 'sm danger', '¿Revocar esta conexión? La IA que la use dejará de tener acceso al instante.')) . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
        if ($super) $actions = post_btn('Revocar todas', $self(['act' => 'revokeall']), 'danger', '¿Revocar TODAS las conexiones de todos los usuarios? Habrá que crearlas de nuevo.');
    }
}
if ($tab === 'registro') {
    $where = '1=1'; $p = [];
    $fu = (int)($_GET['u'] ?? 0); $fs = (string)($_GET['st'] ?? ''); $s = trim((string)($_GET['s'] ?? '')); $fd = (string)($_GET['d'] ?? '');
    if ($fu) { $where .= ' AND l.user_id=?'; $p[] = $fu; }
    if (isset($ST[$fs])) { $where .= ' AND l.status=?'; $p[] = $fs; }
    if ($s !== '') { $where .= ' AND (l.tool LIKE ? OR l.detail LIKE ? OR l.args LIKE ? OR l.ip LIKE ? OR l.method LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%", "%$s%"); }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd)) { $where .= ' AND l.created_at>=? AND l.created_at<=?'; $p[] = $fd . ' 00:00:00'; $p[] = $fd . ' 23:59:59'; }
    $sql = "SELECT l.*, us.name AS uname, t.name AS tname FROM ai_log l LEFT JOIN users us ON us.id=l.user_id LEFT JOIN ai_tokens t ON t.id=l.token_id WHERE $where ORDER BY l.id DESC";
    if (($_GET['do'] ?? '') === 'csv') csv_out('registro-ia', ['Fecha', 'Usuario', 'Perfil', 'Conexión', 'Método', 'Herramienta', 'Resultado', 'Detalle', 'Datos enviados', 'IP', 'Cliente', 'ms'],
        array_map(fn($r) => [$r['created_at'], $r['uname'], ROLES[$r['role']] ?? $r['role'], $r['tname'], $r['method'], $r['tool'], $ST[$r['status']][0] ?? $r['status'], $r['detail'], $r['args'], $r['ip'], $r['client'], $r['ms']], rows($sql . ' LIMIT 20000', $p)));
    $per = 100; $pg = max(1, (int)($_GET['pg'] ?? 1));
    $data = rows($sql . ' LIMIT ' . ($per + 1) . ' OFFSET ' . (($pg - 1) * $per), $p); $more = count($data) > $per; $data = array_slice($data, 0, $per);
    $h .= '<p class="intro">Todo lo que cualquier IA intenta o hace queda aquí: también los intentos sin token, los denegados por permisos y los que llegan con la IA apagada. El registro no se puede editar ni borrar desde la plataforma.</p>'
        . get_form('admin/ai', ['tab' => 'registro'], 'toolbar') . '<input type="search" name="s" value="' . e($s) . '" placeholder="Buscar herramienta, detalle, datos o IP…"><select name="u" data-auto><option value="">Usuario: todos</option>';
    foreach (pairs('SELECT id, name FROM users ORDER BY name') as $k => $l) $h .= '<option value="' . $k . '"' . ($k == $fu ? ' selected' : '') . '>' . e($l) . '</option>';
    $h .= '</select><select name="st" data-auto><option value="">Resultado: todos</option>';
    foreach ($ST as $k => $l) $h .= '<option value="' . $k . '"' . ($k === $fs ? ' selected' : '') . '>' . e($l[0]) . '</option>';
    $h .= '</select><input type="date" name="d" value="' . e($fd) . '"><button class="btn">Filtrar</button></form>';
    if (!$data) $h .= '<div class="empty"><b>' . ($s !== '' || $fu || $fs || $fd ? 'No hay nada con ese filtro.' : 'Todavía no hay actividad de IA.') . '</b></div>';
    else {
        $h .= '<div class="card table"><table class="ailog"><thead><tr><th>Fecha</th><th>Quién</th><th>Qué</th><th>Resultado</th><th>Detalle</th><th>IP</th></tr></thead><tbody>';
        foreach ($data as $r) {
            $what = $r['tool'] !== '' ? '<b>' . e($r['tool']) . '</b>' : e(['panel' => 'Panel', 'initialize' => 'Conexión iniciada', 'tools/list' => 'Consulta de herramientas', 'tools/call' => 'Llamada', 'ping' => 'Comprobación', 'POST' => 'Intento de conexión', 'GET' => 'Intento de conexión'][$r['method']] ?? $r['method']);
            $h .= '<tr><td class="n">' . fdate($r['created_at'], 'd/m/Y H:i:s') . '</td><td>' . ($r['uname'] ? e($r['uname']) . '<br><span class="mute small">' . e(ROLES[$r['role']] ?? '') . ($r['tname'] ? ' · ' . e($r['tname']) : '') . '</span>' : '<span class="mute">Sin identificar</span>') . '</td><td>' . $what . '</td>'
                . '<td>' . badge($ST[$r['status']][0] ?? $r['status'], $ST[$r['status']][1] ?? 'gray') . '</td><td>' . e($r['detail']) . ($r['args'] !== '' && $r['args'] !== null ? '<details><summary>Datos enviados</summary><pre>' . e($r['args']) . '</pre></details>' : '') . '</td>'
                . '<td class="mute small">' . e($r['ip']) . '<br>' . e(mb_strimwidth((string)$r['client'], 0, 28, '…')) . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
        $qp = ['s' => $s, 'u' => $fu ?: null, 'st' => $fs, 'd' => $fd];
        if ($pg > 1 || $more) $h .= '<div class="pager">' . ($pg > 1 ? btn('‹ Más recientes', $self($qp + ['pg' => $pg - 1])) : '') . ($more ? btn('Más antiguos ›', $self($qp + ['pg' => $pg + 1])) : '') . '</div>';
        $actions = btn('Descargar registro', $self(['do' => 'csv', 's' => $s, 'u' => $fu ?: null, 'st' => $fs, 'd' => $fd]));
    }
}
admin_page('Inteligencia artificial', (count($tabsAll) > 1 ? tabs('admin/ai', [], $tabsAll, $tab) : '') . $h, $actions);
