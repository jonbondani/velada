<?php
/**
 * Servidor MCP (Model Context Protocol) de la plataforma. Transporte HTTP, JSON-RPC 2.0.
 *
 * Principios de seguridad:
 *  - Cada conexion usa un token personal. La IA actua como ese usuario y nunca puede mas que el.
 *  - Los permisos se comprueban en cada llamada con la misma funcion can() que usa el panel.
 *  - Todo queda en ai_log: tambien los intentos sin token, los denegados y los hechos con la IA apagada.
 *  - Con el interruptor apagado (ajuste ai_enabled) no se atiende ninguna peticion.
 *
 * Para que el MCP crezca con la app:
 *  - Los recursos se generan desde app/schema.php: un campo nuevo aparece solo.
 *  - Una tabla nueva necesita una linea en mcp_resources() (o en MCP_INTERNAL si no debe exponerse).
 *    mcp_coverage() avisa de lo que falte y las pruebas fallan si queda algo sin clasificar.
 *  - Las acciones llaman a las mismas funciones de app/logic.php que usa el panel.
 */
const MCP_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
// Tablas que nunca se exponen a la IA
const MCP_INTERNAL = ['settings', 'login_attempts', 'ai_tokens', 'ai_log', 'pageviews'];
// Campos que nunca salen ni se pueden escribir, se llamen como se llamen las tablas
const MCP_SECRET = '/pass|token|secret|hash/i';
// A que tabla apunta cada campo de referencia
const MCP_REFS = ['event_id' => 'events', 'venue_id' => 'venues', 'partner_id' => 'partners', 'supplier_id' => 'suppliers', 'customer_id' => 'customers', 'order_id' => 'orders',
    'ticket_type_id' => 'ticket_types', 'account_id' => 'social_accounts', 'supplier_invoice_id' => 'supplier_invoices', 'settlement_id' => 'settlements',
    'manager_id' => 'users', 'assignee_id' => 'users', 'user_id' => 'users', 'created_by' => 'users', 'checked_in_by' => 'users', 'ref_invoice_id' => 'invoices_out'];

class McpError extends RuntimeException {
    public $status;
    public function __construct($msg, $status = 'error') { parent::__construct($msg); $this->status = $status; }
}

/* ---------- Recursos: que tabla, que permiso del panel la protege y que reglas tiene ---------- */
function mcp_resources() {
    static $R = null; if ($R !== null) return $R;
    $k = fn($a) => array_keys($a);
    $R = [
        'eventos' => ['table' => 'events', 'perm' => 'events', 'label' => 'Eventos', 'write' => true, 'scope' => 'events', 'required' => ['title', 'starts_at'], 'search' => ['title', 'subtitle'],
            'readonly' => ['slug', 'image', 'created_at'], 'enums' => ['status' => $k(EVENT_STATUS), 'category' => EVENT_CATS],
            'prepare' => function (array &$d, $rec) {
                $start = $d['starts_at'] ?? ($rec['starts_at'] ?? ''); $end = $d['ends_at'] ?? ($rec['ends_at'] ?? '');
                if (!strtotime((string)$start)) return 'La fecha de inicio (starts_at) no es válida. Usa el formato AAAA-MM-DD HH:MM.';
                if ($end && strtotime((string)$end) < strtotime((string)$start)) return 'La hora de fin no puede ser anterior al inicio.';
                if (isset($d['title']) && (!$rec || $rec['title'] !== $d['title'])) {
                    $base = slugify($d['title']); $slug = $base; $i = 2;
                    while (val('SELECT COUNT(*) FROM events WHERE slug=? AND id<>?', [$slug, $rec['id'] ?? 0])) $slug = $base . '-' . $i++;
                    $d['slug'] = $slug;
                }
                if (!$rec) $d += ['status' => 'draft', 'tax_pct' => setting('tax_pct', '21')];
                if (($d['status'] ?? '') === 'published' && (!$rec || !val('SELECT COUNT(*) FROM ticket_types WHERE event_id=? AND active=1', [$rec['id']]))) return 'Antes de publicar, el evento necesita al menos un tipo de entrada.';
                return null;
            }],
        'tipos_entrada' => ['table' => 'ticket_types', 'perm' => 'events', 'label' => 'Tipos de entrada de un evento', 'write' => true, 'scope' => 'event', 'required' => ['event_id', 'name', 'price'], 'search' => ['name'], 'fixed' => ['event_id'],
            'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['active' => 1, 'max_per_order' => 6, 'seats' => 1]; if (isset($d['seats'])) $d['seats'] = max(1, (int)$d['seats']); return null; }],
        'montaje' => ['table' => 'setup_items', 'perm' => 'events', 'label' => 'Montaje, mobiliario y decoración de un evento', 'write' => true, 'scope' => 'event', 'required' => ['event_id', 'name'], 'search' => ['name', 'category'], 'fixed' => ['event_id'],
            'enums' => ['status' => $k(SETUP_STATUS), 'category' => SETUP_CATS], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['status' => 'pendiente', 'qty' => 1]; return null; }],
        'tareas' => ['table' => 'tasks', 'perm' => 'events', 'label' => 'Tareas de un evento', 'write' => true, 'scope' => 'event', 'required' => ['event_id', 'title'], 'search' => ['title'], 'fixed' => ['event_id'], 'readonly' => ['done_at'],
            'enums' => ['phase' => $k(PHASES)], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['phase' => 'organizacion']; if (array_key_exists('done', $d)) { $d['done'] = $d['done'] ? 1 : 0; $d['done_at'] = $d['done'] ? now() : null; } return null; }],
        'personal_evento' => ['table' => 'staff_assignments', 'perm' => 'events', 'label' => 'Personal y servicios contratados para un evento', 'write' => true, 'scope' => 'event', 'required' => ['event_id', 'supplier_id', 'job'], 'search' => ['job'], 'fixed' => ['event_id'],
            'enums' => ['status' => ['propuesto', 'confirmado', 'realizado']], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['status' => 'propuesto']; return null; }],
        'locales' => ['table' => 'venues', 'perm' => 'venues', 'label' => 'Locales', 'write' => true, 'required' => ['name'], 'search' => ['name', 'city', 'address'], 'readonly' => ['created_at'], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['active' => 1]; return null; }],
        'partners' => ['table' => 'partners', 'perm' => 'partners', 'label' => 'Partners que realizan los eventos', 'write' => true, 'required' => ['name'], 'search' => ['name', 'contact', 'speciality'], 'readonly' => ['created_at'], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['active' => 1]; return null; }],
        'proveedores' => ['table' => 'suppliers', 'perm' => 'suppliers', 'label' => 'Proveedores y personal', 'write' => true, 'required' => ['name'], 'search' => ['name', 'contact', 'category'], 'readonly' => ['created_at'],
            'enums' => ['kind' => ['empresa', 'persona'], 'category' => SUP_CATS], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['active' => 1, 'kind' => 'empresa']; return null; }],
        'clientas' => ['table' => 'customers', 'perm' => 'customers', 'label' => 'Clientas', 'write' => true, 'required' => ['name', 'email'], 'search' => ['name', 'email', 'phone', 'city'], 'readonly' => ['created_at', 'source'],
            'prepare' => function (array &$d, $rec) { if (isset($d['email'])) { $d['email'] = strtolower(trim((string)$d['email'])); if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return 'El correo no parece correcto.'; } if (!$rec) $d['source'] = 'ia'; return null; }],
        'reservas' => ['table' => 'orders', 'perm' => 'orders', 'label' => 'Reservas (se crean y cambian con las acciones crear_reserva, confirmar_pago, cancelar_reserva y devolver_reserva)', 'write' => false, 'search' => ['num', 'discount_code', 'utm_source']],
        'lineas_reserva' => ['table' => 'order_items', 'perm' => 'orders', 'label' => 'Líneas de cada reserva', 'write' => false, 'search' => ['name']],
        'entradas' => ['table' => 'tickets', 'perm' => 'orders', 'label' => 'Entradas emitidas con su código', 'write' => false, 'search' => ['code', 'holder_name', 'type_name']],
        'facturas_emitidas' => ['table' => 'invoices_out', 'perm' => 'finance', 'label' => 'Facturas emitidas a clientas', 'write' => false, 'search' => ['num', 'customer_name', 'concept']],
        'facturas_proveedor' => ['table' => 'supplier_invoices', 'perm' => 'finance', 'label' => 'Facturas recibidas de proveedores (los pagos se registran con registrar_pago_factura)', 'write' => true, 'required' => ['supplier_id', 'num', 'base'], 'search' => ['num', 'concept'],
            'readonly' => ['total', 'paid', 'status', 'file', 'created_at'],
            'prepare' => function (array &$d, $rec) {
                $g = fn($c, $def) => (float)str_replace(',', '.', (string)($d[$c] ?? ($rec[$c] ?? $def)));
                $b = round($g('base', 0), 2); $paid = $rec ? (float)$rec['paid'] : 0;
                if (!$rec) $d += ['tax_pct' => 21, 'issued_at' => date('Y-m-d')];
                $d['total'] = round($b * (1 + $g('tax_pct', 21) / 100) - $b * $g('withholding_pct', 0) / 100, 2);
                $d['status'] = $paid <= 0 ? 'pending' : ($paid >= $d['total'] - 0.005 ? 'paid' : 'partial');
                return null;
            }],
        'pagos' => ['table' => 'payments', 'perm' => 'finance', 'label' => 'Pagos realizados a proveedores y partners', 'write' => false, 'search' => ['reference']],
        'liquidaciones' => ['table' => 'settlements', 'perm' => 'finance', 'label' => 'Liquidaciones a partners (se generan con generar_liquidacion)', 'write' => false],
        'cuentas_redes' => ['table' => 'social_accounts', 'perm' => 'marketing', 'label' => 'Cuentas de redes sociales', 'write' => true, 'required' => ['platform'], 'search' => ['handle'], 'enums' => ['platform' => $k(PLATFORMS)], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['active' => 1]; return null; }],
        'publicaciones' => ['table' => 'social_posts', 'perm' => 'marketing', 'label' => 'Publicaciones planificadas y sus resultados', 'write' => true, 'required' => ['title', 'account_id'], 'search' => ['title', 'body'],
            'enums' => ['status' => $k(POST_STATUS), 'format' => POST_FORMATS], 'prepare' => function (array &$d, $rec) { if (!$rec) $d += ['status' => 'idea', 'format' => 'Publicación']; return null; }],
        'metricas_redes' => ['table' => 'social_metrics', 'perm' => 'marketing', 'label' => 'Seguidores y alcance de cada cuenta por fecha', 'write' => true, 'required' => ['account_id', 'day', 'followers']],
        'codigos_descuento' => ['table' => 'discount_codes', 'perm' => 'marketing', 'label' => 'Códigos promocionales', 'write' => true, 'required' => ['code', 'kind', 'amount'], 'search' => ['code'], 'readonly' => ['used'], 'enums' => ['kind' => ['pct', 'fixed']],
            'prepare' => function (array &$d, $rec) {
                if (isset($d['code'])) { $d['code'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$d['code'])); if ($d['code'] === '') return 'El código solo puede llevar letras y números.'; }
                if (($d['kind'] ?? ($rec['kind'] ?? '')) === 'pct' && (float)($d['amount'] ?? ($rec['amount'] ?? 0)) > 100) return 'Un porcentaje no puede superar el 100 %.';
                if (!$rec) $d += ['active' => 1]; return null;
            }],
        'opiniones' => ['table' => 'feedback', 'perm' => 'stats', 'label' => 'Opiniones de las asistentes', 'write' => false, 'search' => ['liked', 'improve']],
        'usuarios' => ['table' => 'users', 'perm' => 'users', 'label' => 'Usuarios del panel (solo consulta; se gestionan desde el panel)', 'write' => false, 'search' => ['name', 'email']],
        'correos_enviados' => ['table' => 'emails', 'perm' => 'settings', 'label' => 'Registro de correos enviados', 'write' => false, 'hidden' => ['body'], 'search' => ['to_email', 'subject']],
        'actividad' => ['table' => 'activity', 'perm' => 'settings', 'label' => 'Registro de actividad del equipo', 'write' => false, 'search' => ['action', 'detail']],
    ];
    return $R;
}
// Tablas del esquema que nadie ha clasificado todavia. Debe estar siempre vacio.
function mcp_coverage() {
    $known = array_merge(MCP_INTERNAL, array_column(mcp_resources(), 'table'));
    return array_values(array_diff(array_keys(schema()), $known));
}
function mcp_cols(array $res) { return array_keys(array_filter(schema()[$res['table']]['cols'], fn($ty, $c) => !preg_match(MCP_SECRET, $c) && !in_array($c, $res['hidden'] ?? [], true), ARRAY_FILTER_USE_BOTH)); }
if (!function_exists('array_is_list')) { function array_is_list(array $a) { return $a === [] || array_keys($a) === range(0, count($a) - 1); } }
function mcp_writable_cols(array $res) { return array_values(array_diff(mcp_cols($res), ['id'], $res['readonly'] ?? [])); }
// Recursos que este usuario puede ver. 'w' indica si ademas puede modificarlos con este token.
function mcp_my_resources(array $ctx) {
    $out = [];
    foreach (mcp_resources() as $name => $res) if (can($res['perm'])) $out[$name] = $res + ['w' => $res['write'] && !$ctx['readonly'] && can($res['perm'], 'w')];
    return $out;
}
// Filtro de filas: un partner solo ve sus propios eventos y lo que cuelga de ellos (igual que en el panel)
function mcp_scope(array $res) {
    $u = user();
    if ($u['role'] !== 'partner') return ['', []];
    $sc = $res['scope'] ?? '';
    if ($sc === 'events') return [' AND partner_id=?', [(int)$u['partner_id']]];
    if ($sc === 'event') return [' AND event_id IN (SELECT id FROM events WHERE partner_id=?)', [(int)$u['partner_id']]];
    return [' AND 1=0', []];
}
function mcp_res(array $ctx, $name, $write = false) {
    $all = mcp_resources();
    if (!is_string($name) || !isset($all[$name])) throw new McpError('El recurso "' . (is_string($name) ? $name : '') . '" no existe. Usa la herramienta que_puedo_hacer para ver los disponibles.');
    $res = $all[$name];
    if (!can($res['perm'])) throw new McpError('Tu perfil no tiene acceso a "' . $name . '".', 'denied');
    if ($write) {
        if (!$res['write']) throw new McpError('"' . $name . '" no se puede modificar directamente. ' . $res['label'] . '.', 'denied');
        if ($ctx['readonly']) throw new McpError('Esta conexión es de solo consulta: no puede crear, cambiar ni eliminar nada.', 'denied');
        if (!can($res['perm'], 'w')) throw new McpError('Tu perfil solo puede consultar "' . $name . '", no modificarlo.', 'denied');
    }
    return $res;
}
// Tipos homogeneos sea cual sea la base de datos: importes y numeros como numeros, no como texto
function mcp_cast(array $res, array $r) {
    $types = schema()[$res['table']]['cols'];
    foreach ($r as $c => $v) { $ty = $types[$c] ?? 'str'; if ($v === null) continue; if ($ty === 'money') $r[$c] = (float)$v; elseif (in_array($ty, ['int', 'bool', 'pk', 'ref'], true)) $r[$c] = (int)$v; }
    return $r;
}
function mcp_public_row(array $res, array $r) { return mcp_cast($res, array_intersect_key($r, array_flip(mcp_cols($res)))); }

function mcp_find(array $ctx, array $a) {
    $res = mcp_res($ctx, $a['recurso'] ?? ''); $t = $res['table']; $cols = mcp_cols($res);
    [$w, $p] = mcp_scope($res); $where = '1=1' . $w;
    foreach ((array)($a['filtros'] ?? []) as $c => $v) {
        if (!in_array($c, $cols, true)) throw new McpError('No se puede filtrar por "' . $c . '". Campos disponibles: ' . implode(', ', $cols) . '.');
        if ($v === null || $v === '') { $where .= " AND ($c IS NULL OR $c='')"; } else { $where .= " AND $c=?"; $p[] = is_bool($v) ? (int)$v : (is_scalar($v) ? $v : ''); }
    }
    foreach (['desde' => '>=', 'hasta' => '<='] as $key => $op) if (!empty($a[$key]) && is_string($a[$key])) {
        $dc = current(array_intersect(['starts_at', 'created_at', 'issued_at', 'day', 'scheduled_at', 'paid_at', 'due_date'], $cols));
        if (!$dc) throw new McpError('Este recurso no tiene un campo de fecha por el que acotar.');
        $where .= " AND $dc$op?"; $p[] = $a[$key] . ($key === 'hasta' && strlen($a[$key]) === 10 ? ' 23:59:59' : '');
    }
    $txt = trim((string)($a['texto'] ?? ''));
    if ($txt !== '' && !empty($res['search'])) { $where .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $res['search'])) . ')'; foreach ($res['search'] as $_) $p[] = '%' . $txt . '%'; }
    $order = 'id DESC'; $o = (string)($a['orden'] ?? '');
    if ($o !== '') { $desc = $o[0] === '-'; $oc = ltrim($o, '-'); if (!in_array($oc, $cols, true)) throw new McpError('No se puede ordenar por "' . $oc . '".'); $order = $oc . ($desc ? ' DESC' : ' ASC'); }
    $lim = max(1, min(100, (int)($a['limite'] ?? 25))); $pg = max(1, (int)($a['pagina'] ?? 1));
    $total = (int)val("SELECT COUNT(*) FROM $t WHERE $where", $p);
    $data = rows('SELECT ' . implode(',', $cols) . " FROM $t WHERE $where ORDER BY $order LIMIT $lim OFFSET " . (($pg - 1) * $lim), $p);
    return ['recurso' => $a['recurso'], 'total' => $total, 'pagina' => $pg, 'por_pagina' => $lim, 'filas' => array_map(fn($r) => mcp_cast($res, $r), $data)];
}
function mcp_get_row(array $res, $id) {
    [$w, $p] = mcp_scope($res);
    $r = row("SELECT * FROM {$res['table']} WHERE id=?$w", array_merge([(int)$id], $p));
    if (!$r) throw new McpError('No existe ningún registro con ese id, o no tienes acceso a él.');
    return $r;
}
function mcp_save(array $ctx, array $a, $create) {
    $name = $a['recurso'] ?? ''; $res = mcp_res($ctx, $name, true); $t = $res['table'];
    $rec = $create ? null : mcp_get_row($res, $a['id'] ?? 0);
    $d = $a['datos'] ?? null;
    if (!is_array($d) || !$d) throw new McpError('Falta "datos" con los campos a guardar.');
    $ok = mcp_writable_cols($res);
    if ($bad = array_diff(array_keys($d), $ok)) throw new McpError('Estos campos no existen o no se pueden modificar: ' . implode(', ', $bad) . '. Campos admitidos: ' . implode(', ', $ok) . '.');
    foreach ($d as $c => $v) if (is_array($v) || is_object($v)) throw new McpError('El campo "' . $c . '" debe ser un valor simple.');
    if ($create) foreach ($res['required'] ?? [] as $c) if (!isset($d[$c]) || trim((string)$d[$c]) === '') throw new McpError('Falta el campo obligatorio "' . $c . '".');
    if (!$create) foreach ($res['required'] ?? [] as $c) if (array_key_exists($c, $d) && trim((string)$d[$c]) === '') throw new McpError('El campo "' . $c . '" no puede quedar vacío.');
    if (!$create) foreach ($res['fixed'] ?? [] as $c) if (array_key_exists($c, $d) && (string)$d[$c] !== (string)$rec[$c]) throw new McpError('El campo "' . $c . '" no se puede cambiar una vez creado.');
    foreach ($res['enums'] ?? [] as $c => $allowed) if (isset($d[$c]) && $d[$c] !== '' && !in_array($d[$c], $allowed, true)) throw new McpError('Valor no válido para "' . $c . '". Valores admitidos: ' . implode(', ', $allowed) . '.');
    $types = schema()[$t]['cols'];
    foreach ($d as $c => $v) {
        if (in_array($types[$c], ['dt', 'date'], true) && $v !== null && $v !== '' && !strtotime((string)$v)) throw new McpError('La fecha de "' . $c . '" no es válida. Usa AAAA-MM-DD o AAAA-MM-DD HH:MM.');
        if (in_array($types[$c], ['money', 'int'], true) && $v !== null && $v !== '' && !is_numeric(str_replace(',', '.', (string)$v))) throw new McpError('"' . $c . '" debe ser un número.');
        if (in_array($types[$c], ['money', 'int'], true) && (float)str_replace(',', '.', (string)$v) < 0) throw new McpError('"' . $c . '" no puede ser negativo.');
        if ($types[$c] === 'bool') $d[$c] = in_array($v, [true, 1, '1', 'true', 'si', 'sí'], true) ? 1 : 0;
        if ($types[$c] === 'ref' && (int)$v > 0 && isset(MCP_REFS[$c])) {
            $target = MCP_REFS[$c];
            if (!val("SELECT COUNT(*) FROM $target WHERE id=?", [(int)$v])) throw new McpError('"' . $c . '" apunta a un registro que no existe (' . $v . ').');
            // no se puede colgar nada de un evento al que el usuario no tiene acceso
            if ($target === 'events') mcp_get_row(mcp_resources()['eventos'], (int)$v);
        }
    }
    if (isset($res['prepare'])) { $err = $res['prepare']($d, $rec); if ($err) throw new McpError($err); }
    try {
        if ($create) { if (isset($types['created_at'])) $d['created_at'] = now(); $id = insert($t, $d); }
        else { update($t, $d, $rec['id']); $id = $rec['id']; }
    } catch (PDOException $e) { throw new McpError('No se pudo guardar. Puede que ya exista un registro con esos mismos datos.'); }
    log_activity(($create ? 'Alta en ' : 'Cambio en ') . $name, '#' . $id);
    return ['ok' => true, 'id' => (int)$id, 'registro' => mcp_public_row($res, row("SELECT * FROM $t WHERE id=?", [$id]))];
}
function mcp_delete(array $ctx, array $a) {
    $name = $a['recurso'] ?? ''; $res = mcp_res($ctx, $name, true); $t = $res['table']; $rec = mcp_get_row($res, $a['id'] ?? 0); $id = (int)$rec['id'];
    if ($t === 'events') {
        if (val('SELECT COUNT(*) FROM orders WHERE event_id=?', [$id])) throw new McpError('Este evento ya tiene reservas y no se puede eliminar. Puedes cancelarlo con cambiar_estado_evento.');
        foreach (['ticket_types', 'setup_items', 'tasks', 'staff_assignments'] as $child) q("DELETE FROM $child WHERE event_id=?", [$id]);
    }
    // Nada que este en uso se elimina: se busca en todas las tablas que apuntan a este registro
    foreach (schema() as $ot => $def) {
        if (in_array($ot, ['pageviews', 'activity', 'ai_log'], true)) continue;
        foreach ($def['cols'] as $c => $ty) if ($ty === 'ref' && (MCP_REFS[$c] ?? '') === $t && val("SELECT COUNT(*) FROM $ot WHERE $c=?", [$id]))
            throw new McpError('No se puede eliminar porque está en uso (hay registros relacionados en ' . $ot . '). Si existe el campo "active", ponlo a 0 para retirarlo.');
    }
    q("DELETE FROM $t WHERE id=?", [$id]);
    log_activity('Baja en ' . $name, '#' . $id);
    return ['ok' => true, 'eliminado' => $id];
}

/* ---------- Herramientas ---------- */
function mcp_event_for(array $ctx, $id) { mcp_res($ctx, 'eventos'); return mcp_get_row(mcp_resources()['eventos'], $id); }
function mcp_need($module, $lvl, array $ctx) {
    if (!can($module, $lvl)) throw new McpError('Tu perfil no tiene permiso para esta acción.', 'denied');
    if ($lvl === 'w' && $ctx['readonly']) throw new McpError('Esta conexión es de solo consulta.', 'denied');
}
function mcp_order(array $a) { $o = row('SELECT * FROM orders WHERE id=? OR num=?', [(int)($a['reserva_id'] ?? 0), (string)($a['numero'] ?? '')]); if (!$o) throw new McpError('No se encuentra esa reserva.'); return $o; }

function mcp_tools(array $ctx) {
    $mine = mcp_my_resources($ctx); $names = array_keys($mine); $wnames = array_keys(array_filter($mine, fn($r) => $r['w']));
    $obj = fn($props, $req = []) => ['type' => 'object', 'properties' => $props ?: new stdClass, 'required' => $req, 'additionalProperties' => false];
    $int = ['type' => 'integer']; $str = ['type' => 'string']; $num = ['type' => 'number'];
    $ro = ['readOnlyHint' => true]; $wr = ['readOnlyHint' => false, 'destructiveHint' => false]; $ds = ['readOnlyHint' => false, 'destructiveHint' => true];
    $T = [];
    $add = function ($name, $title, $desc, $schema, $ann, $fn) use (&$T) { $T[$name] = ['title' => $title, 'description' => $desc, 'inputSchema' => $schema, 'annotations' => $ann, 'fn' => $fn]; };
    $w = fn($m) => can($m, 'w') && !$ctx['readonly'];

    $add('que_puedo_hacer', 'Qué puedo hacer', 'Devuelve quién eres en la plataforma, tu perfil y la lista de recursos a los que tienes acceso, con sus campos y si puedes modificarlos. Úsala al empezar.', $obj([]), $ro, function () use ($ctx, $mine) {
        $u = user(); $out = [];
        foreach ($mine as $n => $r) {
            $types = schema()[$r['table']]['cols']; $fields = [];
            foreach (mcp_cols($r) as $c) $fields[$c] = ['pk' => 'id', 'str' => 'texto', 'text' => 'texto largo', 'int' => 'entero', 'ref' => 'id de ' . (MCP_REFS[$c] ?? 'otro registro'), 'money' => 'importe', 'dt' => 'fecha y hora', 'date' => 'fecha', 'bool' => '0 o 1'][$types[$c]] . (isset($r['enums'][$c]) ? ' (' . implode(' | ', $r['enums'][$c]) . ')' : '');
            $out[$n] = ['descripcion' => $r['label'], 'puedo_modificar' => $r['w'], 'campos' => $fields, 'obligatorios_al_crear' => $r['w'] ? ($r['required'] ?? []) : [], 'solo_lectura' => $r['w'] ? array_values(array_merge(['id'], $r['readonly'] ?? [])) : 'todos'];
        }
        return ['usuario' => $u['name'], 'perfil' => ROLES[$u['role']] ?? $u['role'], 'conexion_solo_consulta' => $ctx['readonly'], 'recursos' => $out];
    });
    if ($names) {
        $add('buscar', 'Buscar registros', 'Lista registros de un recurso con filtros. Recursos disponibles para ti: ' . implode(', ', $names) . '.', $obj([
            'recurso' => ['type' => 'string', 'enum' => $names], 'filtros' => ['type' => 'object', 'description' => 'Pares campo: valor que deben coincidir exactamente, por ejemplo {"status": "published"}'],
            'texto' => ['type' => 'string', 'description' => 'Texto a buscar en los campos principales'], 'desde' => ['type' => 'string', 'description' => 'Fecha mínima AAAA-MM-DD'], 'hasta' => ['type' => 'string', 'description' => 'Fecha máxima AAAA-MM-DD'],
            'orden' => ['type' => 'string', 'description' => 'Campo por el que ordenar; con un guion delante, descendente. Ejemplo: -starts_at'], 'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'pagina' => ['type' => 'integer', 'minimum' => 1]], ['recurso']), $ro, fn($a) => mcp_find($ctx, $a));
        $add('obtener', 'Ver un registro', 'Devuelve un registro por su id.', $obj(['recurso' => ['type' => 'string', 'enum' => $names], 'id' => $int], ['recurso', 'id']), $ro, function ($a) use ($ctx) { $res = mcp_res($ctx, $a['recurso'] ?? ''); return mcp_public_row($res, mcp_get_row($res, $a['id'] ?? 0)); });
    }
    if ($wnames) {
        $add('crear', 'Crear un registro', 'Crea un registro. Consulta antes los campos con que_puedo_hacer. Recursos que puedes crear: ' . implode(', ', $wnames) . '.', $obj(['recurso' => ['type' => 'string', 'enum' => $wnames], 'datos' => ['type' => 'object', 'description' => 'Pares campo: valor']], ['recurso', 'datos']), $wr, fn($a) => mcp_save($ctx, $a, true));
        $add('actualizar', 'Cambiar un registro', 'Cambia campos de un registro existente. Solo se tocan los campos enviados.', $obj(['recurso' => ['type' => 'string', 'enum' => $wnames], 'id' => $int, 'datos' => ['type' => 'object']], ['recurso', 'id', 'datos']), $wr, fn($a) => mcp_save($ctx, $a, false));
        $add('eliminar', 'Eliminar un registro', 'Elimina un registro de forma definitiva. Se rechaza si el registro está en uso. Pide confirmación a la persona antes de usarla.', $obj(['recurso' => ['type' => 'string', 'enum' => $wnames], 'id' => $int], ['recurso', 'id']), $ds, fn($a) => mcp_delete($ctx, $a));
    }

    if (can('events')) $add('resumen_evento', 'Resumen de un evento', 'Situación de un evento: plazas vendidas, asistencia, avance de tareas y, si tu perfil lo permite, ingresos y presupuesto.', $obj(['evento_id' => $int], ['evento_id']), $ro, function ($a) use ($ctx) {
        $ev = mcp_event_for($ctx, $a['evento_id'] ?? 0); $s = event_stats($ev['id']);
        $out = ['evento' => $ev['title'], 'estado' => EVENT_STATUS[$ev['status']] ?? $ev['status'], 'fecha' => $ev['starts_at'], 'aforo' => (int)$ev['capacity'], 'plazas_vendidas' => $s['sold'], 'plazas_reservadas_sin_pagar' => $s['reserved'], 'plazas_libres' => event_left($ev), 'han_entrado' => $s['checked'],
            'pagina_publica' => abs_url('evento/' . $ev['slug']), 'tareas' => array_map('intval', row('SELECT COUNT(*) AS total, COALESCE(SUM(done),0) AS hechas FROM tasks WHERE event_id=?', [$ev['id']]))];
        if (can('orders') || can('finance')) $out += ['ingresos_cobrados' => $s['revenue'], 'pendiente_de_cobro' => $s['pending']];
        if (can('finance')) {
            $base = round($s['revenue'] / (1 + (float)$ev['tax_pct'] / 100), 2); $c = settlement_calc($ev);
            $plan = (float)val('SELECT COALESCE(SUM(qty*unit_cost),0) FROM setup_items WHERE event_id=?', [$ev['id']]) + (float)val('SELECT COALESCE(SUM(cost),0) FROM staff_assignments WHERE event_id=?', [$ev['id']]) + (float)val('SELECT COALESCE(v.rental_cost,0) FROM events e LEFT JOIN venues v ON v.id=e.venue_id WHERE e.id=?', [$ev['id']]);
            $real = (float)val('SELECT COALESCE(SUM(base),0) FROM supplier_invoices WHERE event_id=?', [$ev['id']]); $partner = $ev['partner_id'] ? $c['amount'] : 0;
            $out['presupuesto'] = ['ingresos_sin_iva' => $base, 'costes_previstos' => $plan, 'costes_segun_facturas' => $real, 'para_el_partner' => $partner, 'resultado' => round($base - ($real > 0 ? $real : $plan) - $partner, 2)];
        }
        return $out;
    });
    if ($w('events')) {
        $add('cambiar_estado_evento', 'Cambiar el estado de un evento', 'Publica, cierra ventas, finaliza, cancela o devuelve a borrador un evento. Estados: ' . implode(', ', array_keys(EVENT_STATUS)) . '. Publicar lo hace visible en la web; cancelar detiene la venta.', $obj(['evento_id' => $int, 'estado' => ['type' => 'string', 'enum' => array_keys(EVENT_STATUS)]], ['evento_id', 'estado']), $ds, function ($a) use ($ctx) {
            $ev = mcp_event_for($ctx, $a['evento_id'] ?? 0); $err = event_set_status($ev, (string)($a['estado'] ?? '')); if ($err) throw new McpError($err);
            return ['ok' => true, 'estado' => EVENT_STATUS[$a['estado']]];
        });
        $add('cargar_tareas_habituales', 'Cargar la lista de tareas habitual', 'Añade a un evento las 20 tareas típicas, repartidas por fases y con fechas calculadas según el día del evento.', $obj(['evento_id' => $int], ['evento_id']), $wr, function ($a) use ($ctx) { $ev = mcp_event_for($ctx, $a['evento_id'] ?? 0); return ['ok' => true, 'tareas_creadas' => event_load_tasks($ev)]; });
        $add('enviar_aviso_asistentes', 'Enviar recordatorio o encuesta', 'Envía por correo a todas las reservas pagadas de un evento un recordatorio ("recordatorio") o la encuesta de opinión ("encuesta"). Son correos reales a clientas: pide confirmación antes.', $obj(['evento_id' => $int, 'tipo' => ['type' => 'string', 'enum' => ['recordatorio', 'encuesta']]], ['evento_id', 'tipo']), $ds, function ($a) use ($ctx) {
            $ev = mcp_event_for($ctx, $a['evento_id'] ?? 0); if (!in_array($a['tipo'] ?? '', ['recordatorio', 'encuesta'], true)) throw new McpError('El tipo debe ser recordatorio o encuesta.');
            $n = event_notify($ev['id'], $a['tipo'] === 'recordatorio' ? 'remind' : 'survey'); log_activity('Aviso a asistentes: ' . $a['tipo'], $ev['title']); return ['ok' => true, 'correos_enviados' => $n];
        });
    }
    if ($w('orders')) {
        $add('crear_reserva', 'Crear una reserva', 'Registra una venta manual o una invitación para un evento. Comprueba aforo y plazas igual que la web. Si "pagada" es verdadero se emiten las entradas y se envían por correo.', $obj([
            'evento_id' => $int, 'entradas' => ['type' => 'array', 'minItems' => 1, 'items' => $obj(['tipo_entrada_id' => $int, 'cantidad' => ['type' => 'integer', 'minimum' => 1]], ['tipo_entrada_id', 'cantidad'])],
            'nombre' => $str, 'email' => $str, 'telefono' => $str, 'forma_pago' => ['type' => 'string', 'enum' => ['cash', 'tpv', 'transfer', 'bizum', 'invite'], 'description' => 'cash efectivo, tpv datáfono, transfer transferencia, bizum, invite invitación sin coste'],
            'pagada' => ['type' => 'boolean'], 'codigo_descuento' => $str, 'notas' => $str], ['evento_id', 'entradas', 'nombre', 'email', 'forma_pago']), $wr, function ($a) use ($ctx) {
            $ev = row('SELECT * FROM events WHERE id=?', [(int)($a['evento_id'] ?? 0)]); if (!$ev) throw new McpError('Ese evento no existe.');
            if (!in_array($ev['status'], ['draft', 'published', 'closed'], true)) throw new McpError('No se puede vender para un evento finalizado o cancelado.');
            $items = []; foreach ((array)($a['entradas'] ?? []) as $it) $items[(int)($it['tipo_entrada_id'] ?? 0)] = ($items[(int)($it['tipo_entrada_id'] ?? 0)] ?? 0) + (int)($it['cantidad'] ?? 0);
            $m = (string)($a['forma_pago'] ?? ''); if (!in_array($m, ['cash', 'tpv', 'transfer', 'bizum', 'invite'], true)) throw new McpError('Forma de pago no válida.');
            $o = create_order($ev, $items, ['name' => $a['nombre'] ?? '', 'email' => $a['email'] ?? '', 'phone' => $a['telefono'] ?? ''], $m, (string)($a['codigo_descuento'] ?? ''), ['admin' => true, 'paid' => !empty($a['pagada']) || $m === 'invite', 'notes' => (string)($a['notas'] ?? ''), 'pay_ref' => 'Venta por IA']);
            log_activity('Venta manual', $o['num']);
            return ['ok' => true, 'reserva_id' => (int)$o['id'], 'numero' => $o['num'], 'estado' => ORDER_STATUS[$o['status']], 'total' => (float)$o['total']];
        });
        $add('confirmar_pago', 'Confirmar el pago de una reserva', 'Marca como pagada una reserva pendiente (Bizum o transferencia recibidos). Emite las entradas con QR, genera la factura y envía el correo a la clienta.', $obj(['reserva_id' => $int, 'numero' => ['type' => 'string', 'description' => 'Número de reserva, por ejemplo P26-00012, si no se conoce el id'], 'referencia' => $str]), $wr, function ($a) {
            $o = mcp_order($a); if ($o['status'] !== 'pending') throw new McpError('La reserva ' . $o['num'] . ' no está pendiente de pago (estado: ' . ORDER_STATUS[$o['status']] . ').');
            mark_paid($o['id'], (string)($a['referencia'] ?? '')); log_activity('Pago confirmado', $o['num']); return ['ok' => true, 'numero' => $o['num'], 'estado' => 'Pagado', 'entradas_emitidas' => (int)val('SELECT COUNT(*) FROM tickets WHERE order_id=?', [$o['id']])];
        });
        $add('cancelar_reserva', 'Cancelar una reserva pendiente', 'Cancela una reserva que aún no se ha pagado y libera sus plazas.', $obj(['reserva_id' => $int, 'numero' => $str]), $ds, function ($a) {
            $o = mcp_order($a); if ($o['status'] !== 'pending') throw new McpError('Solo se pueden cancelar reservas pendientes de pago. Para una pagada usa devolver_reserva.');
            cancel_order($o['id']); log_activity('Reserva cancelada', $o['num']); return ['ok' => true, 'numero' => $o['num'], 'estado' => 'Cancelado'];
        });
        $add('devolver_reserva', 'Devolver una reserva pagada', 'Devuelve una reserva pagada: anula sus entradas y emite factura rectificativa. No se puede deshacer. Pide confirmación expresa a la persona antes de usarla.', $obj(['reserva_id' => $int, 'numero' => $str]), $ds, function ($a) {
            $o = mcp_order($a); [$ok, $msg] = refund_order($o); if (!$ok) throw new McpError($msg); return ['ok' => true, 'numero' => $o['num'], 'mensaje' => $msg];
        });
        $add('reenviar_correo_reserva', 'Reenviar el correo de una reserva', 'Vuelve a enviar a la clienta sus entradas (si está pagada) o las instrucciones de pago (si está pendiente).', $obj(['reserva_id' => $int, 'numero' => $str]), $wr, function ($a) {
            $o = mcp_order($a); if (!in_array($o['status'], ['paid', 'pending'], true)) throw new McpError('La reserva está cancelada o devuelta.');
            $o['status'] === 'paid' ? mail_order_paid($o['id']) : mail_order_pending($o['id']); return ['ok' => true, 'numero' => $o['num']];
        });
    }
    if ($w('checkin')) $add('validar_entrada', 'Validar una entrada', 'Da entrada a una asistente a partir del código de su entrada o de la dirección de su QR. Con "deshacer" verdadero se anula una validación hecha por error.', $obj(['codigo' => $str, 'evento_id' => ['type' => 'integer', 'description' => 'Opcional. Si se indica, se rechazan entradas de otros eventos'], 'deshacer' => ['type' => 'boolean']], ['codigo']), $wr, function ($a) {
        $r = checkin_scan((string)($a['codigo'] ?? ''), (int)($a['evento_id'] ?? 0), !empty($a['deshacer']));
        return ['valida' => $r['ok'], 'resultado' => $r['title'], 'detalle' => $r['text'], 'han_entrado' => $r['counts']['in'] ?? null, 'total_entradas' => $r['counts']['all'] ?? null];
    });
    if ($w('finance')) {
        $pay = ['importe' => $num, 'fecha' => ['type' => 'string', 'description' => 'AAAA-MM-DD. Por defecto, hoy'], 'medio' => ['type' => 'string', 'enum' => array_keys(PAYOUT_METHODS)], 'referencia' => $str];
        $add('registrar_pago_factura', 'Registrar un pago a proveedor', 'Anota un pago, total o parcial, de una factura de proveedor. Solo registra el pago: no mueve dinero.', $obj(['factura_id' => $int] + $pay, ['factura_id', 'importe']), $wr, function ($a) {
            $inv = row('SELECT * FROM supplier_invoices WHERE id=?', [(int)($a['factura_id'] ?? 0)]); if (!$inv) throw new McpError('Esa factura no existe.');
            $rest = round((float)$inv['total'] - (float)$inv['paid'], 2); $amt = round((float)($a['importe'] ?? 0), 2);
            if ($amt <= 0) throw new McpError('El importe debe ser mayor que cero.'); if ($amt > $rest + 0.005) throw new McpError('El importe supera lo que queda por pagar (' . money($rest) . ').');
            pay_supplier_invoice($inv, $amt, (string)($a['fecha'] ?? ''), (string)($a['medio'] ?? 'transfer'), (string)($a['referencia'] ?? ''));
            return ['ok' => true, 'pendiente' => round($rest - $amt, 2)];
        });
        $add('generar_liquidacion', 'Generar la liquidación de un partner', 'Calcula y crea la liquidación de un evento con partner: su porcentaje sobre la venta sin IVA más el importe fijo acordado.', $obj(['evento_id' => $int], ['evento_id']), $wr, function ($a) {
            $id = make_settlement((int)($a['evento_id'] ?? 0)); if (!$id) throw new McpError('No se puede generar: el evento no existe, no tiene partner o ya tiene liquidación.');
            return ['ok' => true, 'liquidacion' => mcp_cast(mcp_resources()['liquidaciones'], row('SELECT id, gross, share_pct, fee, amount, status FROM settlements WHERE id=?', [$id]))];
        });
        $add('registrar_pago_liquidacion', 'Registrar un pago a partner', 'Anota un pago de una liquidación. Solo registra el pago: no mueve dinero.', $obj(['liquidacion_id' => $int] + $pay, ['liquidacion_id', 'importe']), $wr, function ($a) {
            $s = row('SELECT * FROM settlements WHERE id=?', [(int)($a['liquidacion_id'] ?? 0)]); if (!$s) throw new McpError('Esa liquidación no existe.');
            $rest = round((float)$s['amount'] - (float)$s['paid'], 2); $amt = round((float)($a['importe'] ?? 0), 2);
            if ($amt <= 0) throw new McpError('El importe debe ser mayor que cero.'); if ($amt > $rest + 0.005) throw new McpError('El importe supera lo que queda por pagar (' . money($rest) . ').');
            pay_settlement($s, $amt, (string)($a['fecha'] ?? ''), (string)($a['medio'] ?? 'transfer'), (string)($a['referencia'] ?? ''));
            return ['ok' => true, 'pendiente' => round($rest - $amt, 2)];
        });
    }
    if (can('finance')) $add('resumen_finanzas', 'Resumen económico', 'Lo cobrado y pagado este mes, lo que se debe a proveedores (y lo vencido) y lo pendiente de liquidar a partners.', $obj([]), $ro, function () {
        $m0 = date('Y-m-01'); $due = row("SELECT COALESCE(SUM(total-paid),0) AS t, COALESCE(SUM(CASE WHEN due_at<? THEN total-paid ELSE 0 END),0) AS late FROM supplier_invoices WHERE status<>'paid'", [date('Y-m-d')]);
        return ['cobrado_este_mes' => (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid' AND paid_at>=?", [$m0 . ' 00:00:00']), 'pendiente_de_cobro' => (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='pending'"),
            'pagado_este_mes' => (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at>=?', [$m0]), 'por_pagar_a_proveedores' => (float)$due['t'], 'vencido' => (float)$due['late'], 'por_liquidar_a_partners' => (float)val("SELECT COALESCE(SUM(amount-paid),0) FROM settlements WHERE status<>'paid'")];
    });
    if (can('stats')) $add('estadisticas', 'Estadísticas', 'Ingresos, reservas, visitantes de la web, ventas por evento y por canal, y valoración media en un periodo.', $obj(['dias' => ['type' => 'integer', 'enum' => [30, 90, 365], 'description' => 'Periodo en días. Por defecto 90']]), $ro, function ($a) {
        $d = in_array((int)($a['dias'] ?? 90), [30, 90, 365], true) ? (int)($a['dias'] ?? 90) : 90; $from = date('Y-m-d', time() - ($d - 1) * 86400); $fd = $from . ' 00:00:00';
        $o = row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE status='paid' AND paid_at>=?", [$fd]); $ev = []; $ch = [];
        foreach (rows("SELECT e.title, SUM(o.total) AS t FROM orders o JOIN events e ON e.id=o.event_id WHERE o.status='paid' AND o.paid_at>=? GROUP BY e.id, e.title", [$fd]) as $r) $ev[$r['title']] = (float)$r['t'];
        foreach (rows("SELECT utm_source, created_by, SUM(total) AS t FROM orders WHERE status='paid' AND paid_at>=? GROUP BY utm_source, created_by", [$fd]) as $r) { $key = $r['created_by'] ? 'Venta manual' : channel_label($r['utm_source']); $ch[$key] = ($ch[$key] ?? 0) + (float)$r['t']; }
        $fb = row('SELECT COUNT(*) AS n, AVG(rating) AS a FROM feedback WHERE created_at>=?', [$fd]);
        return ['periodo_dias' => $d, 'ingresos' => (float)$o['t'], 'reservas_pagadas' => (int)$o['n'], 'visitantes_web' => (int)val("SELECT COUNT(DISTINCT visitor) FROM pageviews WHERE kind='view' AND day>=?", [$from]),
            'ingresos_por_evento' => $ev, 'ingresos_por_canal' => $ch, 'opiniones' => (int)$fb['n'], 'valoracion_media' => $fb['n'] ? round((float)$fb['a'], 2) : null];
    });
    if (can('settings')) $add('ver_ajustes', 'Ver los ajustes', 'Ajustes generales de la plataforma (nombre, datos fiscales, formas de cobro activas). No incluye claves ni datos secretos, y no se pueden cambiar desde aquí.', $obj([]), $ro, function () {
        $out = []; foreach (rows('SELECT k, v FROM settings ORDER BY k') as $r) if (!preg_match('/stripe|secret|whsec|token|^ai_|schema_ver/i', $r['k'])) $out[$r['k']] = mb_strimwidth((string)$r['v'], 0, 500, '…'); return $out;
    });
    return $T;
}

/* ---------- Registro ---------- */
function mcp_redact($v, $depth = 0) {
    if (is_array($v)) { $o = []; foreach ($v as $k => $x) $o[$k] = is_string($k) && preg_match(MCP_SECRET, $k) ? '***' : ($depth < 6 ? mcp_redact($x, $depth + 1) : '…'); return $o; }
    return is_string($v) ? mb_strimwidth($v, 0, 600, '…') : $v;
}
function mcp_log(array $ctx, $method, $tool, $args, $status, $detail = '') {
    try {
        insert('ai_log', ['created_at' => now(), 'token_id' => $ctx['token_id'] ?? null, 'user_id' => $ctx['user_id'] ?? null, 'role' => $ctx['role'] ?? '', 'ip' => client_ip(), 'client' => mb_substr((string)($ctx['client'] ?? ''), 0, 200),
            'method' => $method, 'tool' => (string)$tool, 'args' => $args === null ? '' : mb_strimwidth(json_encode(mcp_redact($args), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000, '…'),
            'status' => $status, 'detail' => mb_strimwidth((string)$detail, 0, 1000, '…'), 'ms' => (int)round((microtime(true) - $ctx['t0']) * 1000)]);
    } catch (Throwable $e) { @file_put_contents(ROOT . '/storage/error.log', '[' . date('c') . '] ai_log: ' . $e->getMessage() . "\n", FILE_APPEND); }
}

/* ---------- Servidor ---------- */
function mcp_out($status, $body = null, array $headers = []) {
    http_response_code($status); foreach ($headers as $h) header($h);
    if ($body !== null) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    exit;
}
function mcp_err($id, $code, $msg) { return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]]; }
function mcp_bearer() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('apache_request_headers')) foreach (apache_request_headers() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    if (preg_match('/^Bearer\s+(\S+)$/i', trim((string)$h), $m)) return $m[1];
    return trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
}
function mcp_serve() {
    $ctx = ['t0' => microtime(true), 'client' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'readonly' => true];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; $ip = client_ip(); $minAgo = date('Y-m-d H:i:s', time() - 60);
    // Freno a quien insiste sin credenciales validas: no se atiende ni se llena el registro
    if ((int)val("SELECT COUNT(*) FROM ai_log WHERE ip=? AND created_at>=? AND status IN ('auth','off','blocked')", [$ip, $minAgo]) >= 30) mcp_out(429, mcp_err(null, -32000, 'Demasiados intentos. Espera un minuto.'), ['Retry-After: 60']);

    // 1. Interruptor general
    if (!ai_on()) { mcp_log($ctx, $method, '', null, 'off', 'Acceso de IA apagado'); mcp_out(503, mcp_err(null, -32000, 'El acceso de IA está desactivado por la administración de la plataforma.')); }
    // 2. Peticiones desde otra web en un navegador
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')) { mcp_log($ctx, $method, '', null, 'blocked', 'Origen no permitido: ' . $origin); mcp_out(403, mcp_err(null, -32000, 'Origen no permitido.')); }
    // 3. Conexion cifrada obligatoria fuera de un entorno local
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!is_https() && !in_array($host, ['localhost', '127.0.0.1'], true) && !cfg('mcp_allow_http')) { mcp_log($ctx, $method, '', null, 'blocked', 'Conexión sin https'); mcp_out(403, mcp_err(null, -32000, 'El acceso de IA solo funciona por https.')); }
    // 4. Token
    $tok = mcp_bearer();
    $t = $tok !== '' ? row('SELECT * FROM ai_tokens WHERE token_hash=?', [hash('sha256', $tok)]) : null;
    $u = $t ? row('SELECT * FROM users WHERE id=? AND active=1', [$t['user_id']]) : null;
    $why = !$t ? ($tok === '' ? 'Sin token' : 'Token desconocido') : ($t['revoked'] ? 'Token revocado' : ($t['expires_at'] && $t['expires_at'] < now() ? 'Token caducado' : (!$u ? 'Usuario inactivo o eliminado' : (!ai_allowed($u) ? 'Perfil sin permiso para usar IA' : ''))));
    if ($why !== '') {
        mcp_log(($t ? ['token_id' => $t['id'], 'user_id' => $t['user_id']] : []) + $ctx, $method, '', null, 'auth', $why);
        mcp_out(401, mcp_err(null, -32001, 'Token no válido, caducado o sin permiso para usar IA.'), ['WWW-Authenticate: Bearer realm="mcp"']);
    }
    $ctx = ['token_id' => $t['id'], 'user_id' => $u['id'], 'role' => $u['role'], 'readonly' => (bool)$t['readonly']] + $ctx;
    $GLOBALS['ACT_USER'] = $u; $GLOBALS['VIA_AI'] = true;
    q('UPDATE ai_tokens SET last_used_at=? WHERE id=?', [now(), $t['id']]);

    if ($method !== 'POST') { mcp_log($ctx, $method, '', null, 'error', 'Método HTTP no admitido'); mcp_out(405, mcp_err(null, -32000, 'Este servidor solo admite peticiones POST.'), ['Allow: POST']); }
    if ((int)val('SELECT COUNT(*) FROM ai_log WHERE token_id=? AND created_at>=?', [$t['id'], $minAgo]) >= max(10, (int)setting('ai_rate', 120))) { mcp_log($ctx, 'POST', '', null, 'limit', 'Límite de peticiones por minuto'); mcp_out(429, mcp_err(null, -32000, 'Demasiadas peticiones. Espera un minuto.'), ['Retry-After: 60']); }
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 262144) { mcp_log($ctx, 'POST', '', null, 'error', 'Petición demasiado grande'); mcp_out(413, mcp_err(null, -32600, 'Petición demasiado grande.')); }
    $msg = json_decode($raw, true);
    if (!is_array($msg)) { mcp_log($ctx, 'POST', '', null, 'error', 'JSON no válido'); mcp_out(400, mcp_err(null, -32700, 'JSON no válido.')); }
    $batch = array_is_list($msg); $out = [];
    foreach ($batch ? array_slice($msg, 0, 20) : [$msg] as $m) { $r = mcp_handle($ctx, is_array($m) ? $m : []); if ($r !== null) $out[] = $r; }
    if (!$out) mcp_out(202);
    mcp_out(200, $batch ? $out : $out[0]);
}
function mcp_handle(array $ctx, array $m) {
    $id = $m['id'] ?? null; $method = is_string($m['method'] ?? null) ? $m['method'] : ''; $p = is_array($m['params'] ?? null) ? $m['params'] : [];
    $isNote = !array_key_exists('id', $m);
    $ok = fn($result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    if (($m['jsonrpc'] ?? '') !== '2.0' || $method === '') { mcp_log($ctx, $method, '', null, 'error', 'Mensaje JSON-RPC no válido'); return $isNote ? null : mcp_err($id, -32600, 'Mensaje no válido.'); }
    if ($isNote) { mcp_log($ctx, $method, '', null, 'ok'); return null; }
    switch ($method) {
        case 'initialize':
            $ctx['client'] = trim(($p['clientInfo']['name'] ?? '') . ' ' . ($p['clientInfo']['version'] ?? '')) ?: $ctx['client'];
            mcp_log($ctx, $method, '', ['protocolVersion' => $p['protocolVersion'] ?? ''], 'ok');
            return $ok(['protocolVersion' => in_array($p['protocolVersion'] ?? '', MCP_VERSIONS, true) ? $p['protocolVersion'] : MCP_VERSIONS[0], 'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'velada', 'title' => brand(), 'version' => APP_VER],
                'instructions' => 'Plataforma de venta y gestión de eventos de ' . brand() . '. Actúas en nombre de ' . user()['name'] . ' (perfil ' . (ROLES[user()['role']] ?? '') . ') y solo puedes hacer lo que ese perfil puede hacer en el panel. Empieza con que_puedo_hacer. Los importes están en euros y las fechas en formato AAAA-MM-DD HH:MM, hora de España. Antes de eliminar, devolver, cancelar o enviar correos a clientas, pide confirmación a la persona. Todas tus acciones quedan registradas.']);
        case 'ping': mcp_log($ctx, $method, '', null, 'ok'); return $ok(new stdClass);
        case 'tools/list':
            $tools = []; foreach (mcp_tools($ctx) as $n => $t) $tools[] = ['name' => $n, 'title' => $t['title'], 'description' => $t['description'], 'inputSchema' => $t['inputSchema'], 'annotations' => $t['annotations'] + ['title' => $t['title']]];
            mcp_log($ctx, $method, '', null, 'ok', count($tools) . ' herramientas'); return $ok(['tools' => $tools]);
        case 'tools/call':
            $name = is_string($p['name'] ?? null) ? $p['name'] : ''; $args = is_array($p['arguments'] ?? null) ? $p['arguments'] : [];
            $all = mcp_tools($ctx);
            $text = fn($v, $err = false) => $ok(['content' => [['type' => 'text', 'text' => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]], 'isError' => $err]);
            if (!isset($all[$name])) {
                // Si la herramienta existe para otros perfiles es un intento sin permiso; si no, un nombre equivocado
                $me = $GLOBALS['ACT_USER']; $GLOBALS['ACT_USER'] = ['role' => 'admin'] + $me;
                try { $exists = isset(mcp_tools(['readonly' => false] + $ctx)[$name]); } finally { $GLOBALS['ACT_USER'] = $me; }
                mcp_log($ctx, $method, $name, $args, $exists ? 'denied' : 'error', $exists ? 'Sin permiso para esta herramienta' : 'Herramienta desconocida');
                return $text($exists ? 'No tienes permiso para usar "' . $name . '" con este perfil o con esta conexión.' : 'La herramienta "' . $name . '" no existe.', true);
            }
            try { $res = $all[$name]['fn']($args); mcp_log($ctx, $method, $name, $args, 'ok', is_array($res) && isset($res['total']) ? $res['total'] . ' resultados' : ''); return $text($res); }
            catch (McpError $e) { mcp_log($ctx, $method, $name, $args, $e->status, $e->getMessage()); return $text($e->getMessage(), true); }
            catch (PDOException $e) {
                @file_put_contents(ROOT . '/storage/error.log', '[' . date('c') . '] mcp ' . $name . ': ' . $e->getMessage() . "\n", FILE_APPEND);
                mcp_log($ctx, $method, $name, $args, 'error', 'Error de base de datos: ' . $e->getMessage()); return $text('Ha ocurrido un error interno. La acción no se ha completado.', true);
            }
            catch (RuntimeException $e) { mcp_log($ctx, $method, $name, $args, 'error', $e->getMessage()); return $text($e->getMessage(), true); }
            catch (Throwable $e) {
                @file_put_contents(ROOT . '/storage/error.log', '[' . date('c') . '] mcp ' . $name . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
                mcp_log($ctx, $method, $name, $args, 'error', 'Error interno: ' . $e->getMessage()); return $text('Ha ocurrido un error interno. La acción no se ha completado.', true);
            }
        default: mcp_log($ctx, $method, '', null, 'error', 'Método no admitido'); return mcp_err($id, -32601, 'Método no admitido: ' . $method);
    }
}
