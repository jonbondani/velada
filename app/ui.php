<?php
// Interfaz: plantillas del panel y de la web publica, motor de formularios y listados, graficos

function icon($n) {
    $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h14V10', 'events' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
        'venues' => 'M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11zM12 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
        'partners' => 'M16 20v-1.5a3.5 3.5 0 00-3.5-3.5h-5A3.5 3.5 0 004 18.5V20M10 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM20 20v-1.5a3.5 3.5 0 00-2.6-3.4M15.5 4.2a3.5 3.5 0 010 6.6',
        'suppliers' => 'M3 8l9-5 9 5v8l-9 5-9-5zM3 8l9 5 9-5M12 13v8', 'orders' => 'M5 8h14l-1 12H6zM9 8V6a3 3 0 016 0v2',
        'customers' => 'M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z',
        'checkin' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2M14 18h2v2M18 18h2v2',
        'finance' => 'M17 6.5A6.5 6.5 0 007 12a6.5 6.5 0 0010 5.5M4 10.5h9M4 13.5h9', 'marketing' => 'M4 10v4h3l8 4V6l-8 4zM18 9a4 4 0 010 6',
        'stats' => 'M4 20V10M10 20V4M16 20v-7M21 20H3', 'users' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM5 20a7 7 0 0114 0',
        'settings' => 'M4 7h10M18 7h2M4 17h4M12 17h8M16 5v4M10 15v4', 'dashboard' => 'M3 11l9-8 9 8M5 10v10h14V10',
    ];
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$n] ?? '') . '"/></svg>';
}
function module_route($m) { return $m === 'dashboard' ? 'admin' : 'admin/' . $m; }

function admin_page($title, $content, $actions = '', $back = '') {
    $u = user(); $cur = $GLOBALS['MODULE'] ?? '';
    $groups = ['' => ['dashboard'], 'Eventos' => ['events', 'venues', 'partners', 'suppliers'], 'Ventas' => ['orders', 'customers', 'checkin'],
        'Negocio' => ['finance', 'marketing', 'stats'], 'Sistema' => ['users', 'settings']];
    $nav = '';
    foreach ($groups as $g => $mods) {
        $items = '';
        foreach ($mods as $m) if (can($m)) {
            $label = ($m === 'events' && ($u['role'] ?? '') === 'partner') ? 'Mis eventos' : MODULES[$m];
            $items .= '<a data-m="' . $m . '" href="' . e(url(module_route($m))) . '"' . ($cur === $m ? ' class="on"' : '') . '>' . icon($m) . '<span>' . e($label) . '</span></a>';
        }
        if ($items) $nav .= ($g ? '<div class="grp">' . e($g) . '</div>' : '') . $items;
    }
    $tourJs = '';
    if ($u) {
        $nav .= '<a id="helplink" data-m="help" href="' . e(url('admin/help')) . '"' . ($cur === 'help' ? ' class="on"' : '') . '><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.2M12 17h.01"/></svg><span>Ayuda y tutorial</span></a>';
        require_once ROOT . '/app/tours.php';
        $tourJs = '<script>window.TOURS=' . json_encode(tours(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';window.TOUR_AUTO=' . (empty($u['tour_done']) ? 'true' : 'false') . ';window.TOUR_DONE=' . json_encode(url('admin/help', ['act' => 'done'])) . ';</script>';
    }
    $flash = '';
    foreach (take_flash() as $f) $flash .= '<div class="flash ' . e($f[0]) . '">' . e($f[1]) . '</div>';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' · ' . e(brand()) . '</title><link rel="stylesheet" href="' . asset('admin.css') . '"></head>'
        . '<body><aside class="side" id="side"><a class="brand" href="' . e(url('admin')) . '">' . e(brand()) . '</a><nav>' . $nav . '</nav>'
        . ($u ? '<div class="me"><b>' . e($u['name']) . '</b><span>' . e(ROLES[$u['role']] ?? '') . '</span><div><a href="' . e(url('')) . '" target="_blank">Ver web</a> · <a href="' . e(url('salir')) . '">Salir</a></div></div>' : '')
        . '</aside><div class="shade" id="shade"></div><main class="main"><header class="top"><button class="menu" id="menu" aria-label="Menú"><span></span></button><div class="ttl">'
        . ($back ? '<a class="back" href="' . e($back) . '">‹ Volver</a>' : '') . '<h1>' . e($title) . '</h1></div><div class="actions">' . $actions . '</div></header>'
        . $flash . $content . '</main><script>window.CSRF="' . csrf() . '";</script>' . $tourJs . '<script src="' . asset('app.js') . '"></script></body></html>';
}
function btn($label, $href, $class = '') { return '<a class="btn ' . $class . '" href="' . e($href) . '">' . e($label) . '</a>'; }
function badge($text, $color = 'gray') { return '<span class="badge ' . $color . '">' . e($text) . '</span>'; }
function kpi($label, $value, $sub = '') { return '<div class="kpi"><span>' . e($label) . '</span><b>' . $value . '</b>' . ($sub !== '' ? '<small>' . $sub . '</small>' : '') . '</div>'; }
function progress($pct, $color = '') { return '<div class="bar ' . $color . '"><i style="width:' . max(0, min(100, round($pct))) . '%"></i></div>'; }
function tabs($route, array $params, array $tabs, $cur, $key = 'tab') {
    $h = '<div class="tabs">';
    foreach ($tabs as $k => $l) $h .= '<a href="' . e(url($route, [$key => $k] + $params)) . '"' . ($k === $cur ? ' class="on"' : '') . '>' . e($l) . '</a>';
    return $h . '</div>';
}
function get_form($route, array $params = [], $class = '') {
    $h = '<form method="get" class="' . $class . '" action="' . e(cfg('pretty', true) ? url($route) : base_path() . '/index.php') . '">';
    if (!cfg('pretty', true)) $params = ['r' => $route] + $params;
    foreach ($params as $k => $v) if ($v !== null && $v !== '') $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h;
}
function post_btn($label, $href, $class = '', $confirm = '') {
    return '<form method="post" action="' . e($href) . '" class="inline">' . csrf_field() . '<button class="btn ' . $class . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . e($label) . '</button></form>';
}

/* ---------- Motor generico de listados y formularios ---------- */
function field_options(array $f) { $o = $f['options'] ?? []; return is_callable($o) ? $o() : $o; }
function cell(array $f, $v, array $opts = []) {
    switch ($f['type'] ?? 'text') {
        case 'money': return '<span class="n">' . money($v) . '</span>';
        case 'pct': return '<span class="n">' . num($v, (float)$v == (int)$v ? 0 : 2) . ' %</span>';
        case 'number': return '<span class="n">' . e($v) . '</span>';
        case 'date': return e(fdate($v));
        case 'datetime': return e(fdt($v));
        case 'check': return $v ? 'Sí' : '<span class="mute">No</span>';
        case 'select': $l = $opts[$v] ?? ''; return isset($f['badge']) && $l !== '' ? badge($l, $f['badge'][$v] ?? 'gray') : e($l);
        case 'textarea': return e(mb_strimwidth((string)$v, 0, 70, '…'));
        default: return e($v);
    }
}
function render_field($k, array $f, $v) {
    $ty = $f['type'] ?? 'text'; $req = !empty($f['req']) ? ' required' : ''; $ph = isset($f['ph']) ? ' placeholder="' . e($f['ph']) . '"' : '';
    $help = isset($f['help']) ? '<small>' . e($f['help']) . '</small>' : ''; $full = !empty($f['full']) || in_array($ty, ['textarea', 'static']) ? ' full' : '';
    if ($ty === 'static') { $out = $f['render']($v); return $out === '' ? '' : '<div class="fld full">' . $out . '</div>'; }
    if ($ty === 'check') return '<label class="fld chk' . $full . '"><input type="checkbox" name="' . $k . '" value="1"' . ($v ? ' checked' : '') . '><span>' . e($f['label']) . '</span>' . $help . '</label>';
    $in = '';
    switch ($ty) {
        case 'textarea': $in = '<textarea name="' . $k . '" rows="' . ($f['rows'] ?? 4) . '"' . $req . $ph . '>' . e($v) . '</textarea>'; break;
        case 'select':
            $in = '<select name="' . $k . '"' . $req . '>' . (empty($f['req']) || $v === '' || $v === null ? '<option value="">' . e($f['empty'] ?? 'Sin asignar') . '</option>' : '');
            foreach (field_options($f) as $ok => $ol) $in .= '<option value="' . e($ok) . '"' . ((string)$ok === (string)$v ? ' selected' : '') . '>' . e($ol) . '</option>';
            $in .= '</select>'; break;
        case 'money': case 'pct': $in = '<input type="number" step="0.01" min="0" inputmode="decimal" name="' . $k . '" value="' . e($v === '' || $v === null ? '' : (float)$v) . '"' . $req . $ph . '>'; break;
        case 'number': $in = '<input type="number" step="1" inputmode="numeric" name="' . $k . '" value="' . e($v) . '"' . $req . $ph . '>'; break;
        case 'date': $in = '<input type="date" name="' . $k . '" value="' . e(substr((string)$v, 0, 10)) . '"' . $req . '>'; break;
        case 'datetime': $in = '<input type="datetime-local" name="' . $k . '" value="' . e(str_replace(' ', 'T', substr((string)$v, 0, 16))) . '"' . $req . '>'; break;
        case 'image':
            $in = ($v ? '<div class="thumb"><img src="' . e(base_path() . '/uploads/' . $v) . '" alt=""><label class="chk"><input type="checkbox" name="' . $k . '_del" value="1"> Quitar imagen</label></div>' : '')
                . '<input type="file" name="' . $k . '" accept="image/jpeg,image/png,image/webp">'; break;
        case 'file':
            $in = ($v ? '<div class="thumb"><a href="' . e(url('admin/file', ['f' => $v])) . '" target="_blank">Ver documento adjunto</a></div>' : '')
                . '<input type="file" name="' . $k . '" accept="application/pdf,image/jpeg,image/png">'; break;
        default: $in = '<input type="' . (in_array($ty, ['email', 'tel', 'url', 'color', 'password']) ? $ty : 'text') . '" name="' . $k . '" value="' . ($ty === 'password' ? '' : e($v)) . '"' . ($ty === 'password' ? ' autocomplete="new-password" placeholder="' . ($v ? 'Guardado. Escribe solo para cambiarlo' : '') . '"' : ' maxlength="255"') . $req . $ph . '>';
    }
    return '<label class="fld' . $full . '"><span>' . e($f['label']) . (!empty($f['req']) ? '' : '') . '</span>' . $in . $help . '</label>';
}

function crud(array $c) {
    $c += ['perm' => 'settings', 'scope' => [], 'params' => [], 'order' => 'id DESC', 'search' => [], 'singular' => 'registro', 'filters' => [], 'deletable' => true, 'cols' => [], 'new' => 'Añadir'];
    $t = $c['table']; $w = can($c['perm'], 'w') && empty($c['readonly']);
    $U = fn($x = []) => url($c['route'], $x + $c['params']);
    $do = $_GET['do'] ?? 'list'; $rid = (int)($_GET['rid'] ?? 0);
    $scopeSql = ''; $scopeP = [];
    foreach ($c['scope'] as $k => $v) { $scopeSql .= " AND $k=?"; $scopeP[] = $v; }
    $rec = $rid ? row("SELECT * FROM $t WHERE id=?$scopeSql", array_merge([$rid], $scopeP)) : null;
    $errors = [];

    if (is_post() && in_array($do, ['save', 'delete'], true)) {
        if (!$w) need($c['perm'], 'w');
        if ($do === 'delete') {
            if ($rec && $c['deletable']) {
                try { if (isset($c['before_delete'])) $c['before_delete']($rec); q("DELETE FROM $t WHERE id=?", [$rid]); flash('Eliminado correctamente.'); }
                catch (RuntimeException $e) { flash($e->getMessage(), 'err'); redirect($U(['do' => 'edit', 'rid' => $rid])); }
            }
            redirect($U());
        }
        $d = [];
        foreach ($c['fields'] as $k => $f) {
            $ty = $f['type'] ?? 'text';
            if ($ty === 'static') continue;
            if ($ty === 'check') { $d[$k] = isset($_POST[$k]) ? 1 : 0; continue; }
            if ($ty === 'image' || $ty === 'file') {
                try {
                    $n = upload($k, $ty === 'image' ? ROOT . '/uploads' : ROOT . '/storage/files', $ty === 'image' ? ['jpg', 'png', 'webp'] : ['pdf', 'jpg', 'png']);
                    if ($n !== '') $d[$k] = $n; elseif (!empty($_POST[$k . '_del'])) $d[$k] = '';
                } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
                continue;
            }
            $v = $_POST[$k] ?? ''; $v = is_array($v) ? '' : trim($v);
            if (!empty($f['req']) && $v === '') $errors[] = 'Falta completar "' . $f['label'] . '".';
            if ($ty === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[] = 'El correo de "' . $f['label'] . '" no parece correcto.';
            if ($ty === 'select' && $v !== '' && !array_key_exists($v, field_options($f))) $v = '';
            $d[$k] = $v;
        }
        if (!$errors && isset($c['validate'])) { $msg = $c['validate']($d, $rec); if ($msg) $errors[] = $msg; }
        if (!$errors) {
            try {
                if ($rec) { update($t, $d, $rid); $id = $rid; }
                else { $d += $c['scope']; if (isset(schema()[$t]['cols']['created_at'])) $d['created_at'] = now(); $id = insert($t, $d); }
                if (isset($c['after_save'])) $c['after_save']($id, $d, !$rec);
                flash('Cambios guardados.');
                redirect(isset($c['after']) ? $c['after']($id) : $U());
            } catch (PDOException $e) { $errors[] = 'No se pudo guardar. Puede que ya exista un registro con esos mismos datos.'; }
        }
        $rec = array_merge($rec ?? [], $d); if ($rid) $rec['id'] = $rid;
        $do = $rid ? 'edit' : 'new';
    }

    /* Formulario */
    if ($do === 'edit' || $do === 'new') {
        if ($do === 'edit' && !$rec) redirect($U());
        if ($do === 'new' && !$w) redirect($U());
        $h = '';
        if ($errors) $h .= '<div class="flash err">' . implode('<br>', array_map('e', $errors)) . '</div>';
        $h .= '<form method="post" enctype="multipart/form-data" action="' . e($U(['do' => 'save', 'rid' => $rid ?: null])) . '" class="card form">' . csrf_field()
            . '<h2>' . e(($rid ? ($w ? 'Editar ' : '') : 'Añadir ') . $c['singular']) . '</h2><fieldset' . ($w ? '' : ' disabled') . '><div class="grid">';
        foreach ($c['fields'] as $k => $f) {
            if (isset($f['section'])) $h .= '<h3 class="full">' . e($f['section']) . '</h3>';
            $h .= render_field($k, $f, $rec[$k] ?? ($rid ? '' : ($f['default'] ?? '')));
        }
        $h .= '</div></fieldset><div class="form-foot">';
        if ($w) $h .= '<button class="btn primary">Guardar</button>';
        $h .= btn($w ? 'Cancelar' : 'Volver', $U());
        if ($w && $rid && $c['deletable']) $h .= '<button class="btn danger push" formnovalidate formaction="' . e($U(['do' => 'delete', 'rid' => $rid])) . '" data-confirm="¿Seguro que quieres eliminarlo? Esta acción no se puede deshacer.">Eliminar</button>';
        $h .= '</div></form>';
        if ($rid && isset($c['form_extra'])) $h .= $c['form_extra']($rec);
        return ['html' => $h, 'actions' => '', 'mode' => 'form'];
    }

    /* Listado */
    $where = "1=1$scopeSql"; $p = $scopeP;
    $s = trim((string)($_GET['s'] ?? ''));
    if ($s !== '' && $c['search']) {
        $where .= ' AND (' . implode(' OR ', array_map(fn($col) => "$col LIKE ?", $c['search'])) . ')';
        foreach ($c['search'] as $_) $p[] = '%' . $s . '%';
    }
    $fsel = [];
    foreach ($c['filters'] as $fk) {
        $fv = (string)($_GET['f_' . $fk] ?? ''); $fsel[$fk] = $fv;
        if ($fv !== '') { $where .= " AND $fk=?"; $p[] = $fv; }
    }
    if (isset($c['where'])) { $where .= ' AND ' . $c['where'][0]; $p = array_merge($p, $c['where'][1]); }
    $per = 50; $pg = max(1, (int)($_GET['pg'] ?? 1));
    $data = rows("SELECT * FROM $t WHERE $where ORDER BY {$c['order']} LIMIT " . ($per + 1) . ' OFFSET ' . (($pg - 1) * $per), $p);
    $more = count($data) > $per; $data = array_slice($data, 0, $per);

    $cols = array_filter($c['fields'], fn($f) => !empty($f['list']));
    $optCache = [];
    foreach ($cols as $k => $f) if (($f['type'] ?? '') === 'select') $optCache[$k] = field_options($f);

    $h = '';
    if (!empty($c['intro'])) $h .= '<p class="intro">' . e($c['intro']) . '</p>';
    if ($c['search'] || $c['filters']) {
        $h .= get_form($c['route'], $c['params'], 'toolbar');
        if ($c['search']) $h .= '<input type="search" name="s" value="' . e($s) . '" placeholder="Buscar…">';
        foreach ($c['filters'] as $fk) {
            $h .= '<select name="f_' . $fk . '" data-auto><option value="">' . e($c['fields'][$fk]['label']) . ': todos</option>';
            foreach (field_options($c['fields'][$fk]) as $ok => $ol) $h .= '<option value="' . e($ok) . '"' . ((string)$ok === $fsel[$fk] ? ' selected' : '') . '>' . e($ol) . '</option>';
            $h .= '</select>';
        }
        $h .= '<button class="btn">Buscar</button></form>';
    }
    if (!$data) {
        $h .= '<div class="empty"><b>' . ($s !== '' || array_filter($fsel) ? 'No hay resultados con ese filtro.' : 'Todavía no hay nada aquí.') . '</b>'
            . ($w && !empty($c['empty']) ? '<br>' . e($c['empty']) : '') . ($w ? '<p>' . btn($c['new'], $U(['do' => 'new']), 'primary') . '</p>' : '') . '</div>';
    } else {
        $h .= '<div class="card table"><table><thead><tr>';
        foreach ($cols as $f) $h .= '<th' . (in_array($f['type'] ?? '', ['money', 'pct', 'number']) ? ' class="r"' : '') . '>' . e($f['short'] ?? $f['label']) . '</th>';
        foreach ($c['cols'] as $label => $_) $h .= '<th>' . e($label) . '</th>';
        $h .= '</tr></thead><tbody>';
        foreach ($data as $r) {
            $link = isset($c['row_url']) ? $c['row_url']($r) : $U(['do' => 'edit', 'rid' => $r['id']]);
            $h .= '<tr data-href="' . e($link) . '">'; $first = true;
            foreach ($cols as $k => $f) {
                $v = cell($f, $r[$k], $optCache[$k] ?? []);
                $h .= '<td' . (in_array($f['type'] ?? '', ['money', 'pct', 'number']) ? ' class="r"' : '') . '>' . ($first ? '<a class="lead" href="' . e($link) . '">' . ($v !== '' ? $v : '(sin nombre)') . '</a>' : $v) . '</td>';
                $first = false;
            }
            foreach ($c['cols'] as $fn) $h .= '<td>' . $fn($r) . '</td>';
            $h .= '</tr>';
        }
        $h .= '</tbody>' . (isset($c['footer']) ? '<tfoot>' . $c['footer']($data) . '</tfoot>' : '') . '</table></div>';
        if ($pg > 1 || $more) {
            $qp = ['s' => $s]; foreach ($fsel as $fk => $fv) $qp['f_' . $fk] = $fv;
            $h .= '<div class="pager">' . ($pg > 1 ? btn('‹ Anteriores', $U($qp + ['pg' => $pg - 1])) : '') . ($more ? btn('Siguientes ›', $U($qp + ['pg' => $pg + 1])) : '') . '</div>';
        }
    }
    return ['html' => $h, 'actions' => $w ? btn($c['new'], $U(['do' => 'new']), 'primary') : '', 'mode' => 'list'];
}
function crud_page($title, array $c, $extraActions = '') {
    $r = crud($c);
    admin_page($title, $r['html'], $r['mode'] === 'list' ? $extraActions . $r['actions'] : '', $r['mode'] === 'form' ? url($c['route'], $c['params'] ?? []) : '');
}

/* ---------- Graficos SVG sin dependencias ---------- */
function chart_bars(array $data, $fmt = null, $color = 'var(--accent)') {
    if (!$data) return '<div class="nochart">Sin datos todavía</div>';
    $fmt = $fmt ?: fn($v) => num($v);
    $W = 720; $H = 220; $pt = 22; $pb = 26; $n = count($data); $max = max(1, max($data));
    $slot = $W / $n; $bw = min(46, $slot * 0.62); $ih = $H - $pt - $pb; $i = 0; $s = '';
    $skip = (int)ceil($n / 12);
    foreach ($data as $l => $v) {
        $x = $i * $slot + ($slot - $bw) / 2; $bh = $v > 0 ? max(2, $ih * $v / $max) : 0; $y = $pt + $ih - $bh;
        $s .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round($bh, 1) . '" rx="4" fill="' . $color . '"><title>' . e($l . ': ' . $fmt($v)) . '</title></rect>';
        if ($n <= 14 && $v > 0) $s .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . round($y - 6, 1) . '" text-anchor="middle" class="cv">' . e($fmt($v)) . '</text>';
        if ($i % $skip === 0) $s .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($H - 8) . '" text-anchor="middle" class="cl">' . e($l) . '</text>';
        $i++;
    }
    return '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img"><line x1="0" x2="' . $W . '" y1="' . ($pt + $ih) . '" y2="' . ($pt + $ih) . '" class="ax"/>' . $s . '</svg>';
}
function chart_line(array $data, $fmt = null, $color = 'var(--accent)') {
    if (count($data) < 2) return '<div class="nochart">Aún no hay datos suficientes para dibujar la evolución</div>';
    $fmt = $fmt ?: fn($v) => num($v);
    $W = 720; $H = 220; $pt = 22; $pb = 26; $n = count($data); $vals = array_values($data); $labels = array_keys($data);
    $max = max($vals); $min = min($vals); $lo = $min > 0 && $min > $max * 0.5 ? $min * 0.98 : 0; $rng = max(0.0001, $max - $lo); $ih = $H - $pt - $pb;
    $pts = [];
    foreach ($vals as $i => $v) $pts[] = [round($i * ($W - 16) / ($n - 1) + 8, 1), round($pt + $ih - $ih * ($v - $lo) / $rng, 1)];
    $line = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));
    $s = '<polygon points="8,' . ($pt + $ih) . ' ' . $line . ' ' . ($W - 8) . ',' . ($pt + $ih) . '" fill="' . $color . '" opacity=".1"/><polyline points="' . $line . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
    $skip = (int)ceil($n / 8);
    foreach ($pts as $i => $p) {
        $s .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="' . ($n <= 31 ? 3 : 0) . '" fill="' . $color . '"><title>' . e($labels[$i] . ': ' . $fmt($vals[$i])) . '</title></circle>';
        if ($i % $skip === 0) $s .= '<text x="' . $p[0] . '" y="' . ($H - 8) . '" text-anchor="' . ($i === 0 ? 'start' : 'middle') . '" class="cl">' . e($labels[$i]) . '</text>';
    }
    $s .= '<text x="' . ($W - 8) . '" y="14" text-anchor="end" class="cv">Máx. ' . e($fmt($max)) . '</text>';
    return '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img"><line x1="0" x2="' . $W . '" y1="' . ($pt + $ih) . '" y2="' . ($pt + $ih) . '" class="ax"/>' . $s . '</svg>';
}
function hbars(array $data, $fmt = null, $limit = 8) {
    if (!$data) return '<div class="nochart">Sin datos todavía</div>';
    $fmt = $fmt ?: fn($v) => num($v);
    arsort($data); $data = array_slice($data, 0, $limit, true); $max = max(1, max($data)); $h = '<div class="hbars">';
    foreach ($data as $l => $v) $h .= '<div><span>' . e($l) . '</span><i><b style="width:' . round(100 * $v / $max, 1) . '%"></b></i><em>' . e($fmt($v)) . '</em></div>';
    return $h . '</div>';
}
function panel($title, $body, $class = '') { return '<section class="card panel ' . $class . '">' . ($title !== '' ? '<h2>' . e($title) . '</h2>' : '') . $body . '</section>'; }
function stars($v) { $v = (float)$v; return '<span class="stars" title="' . num($v, 1) . ' de 5">' . str_repeat('★', (int)round($v)) . '<i>' . str_repeat('★', 5 - (int)round($v)) . '</i></span>'; }

/* ---------- Web publica ---------- */
function event_art(array $ev, $class = 'art') {
    if (!empty($ev['image'])) return '<div class="' . $class . '" style="background-image:url(\'' . e(base_path() . '/uploads/' . $ev['image']) . '\')"></div>';
    $palettes = [['#e9d5ca', '#c9a59a', '#8f6a62'], ['#ead9c3', '#d2b48c', '#9a7b54'], ['#e3d6dc', '#c4a3b1', '#84606f'], ['#dfe3d6', '#b7c2a4', '#70805f'], ['#e6dccf', '#cdb7a1', '#7d6a58']];
    $p = $palettes[crc32((string)$ev['title']) % count($palettes)];
    return '<div class="' . $class . ' gen" style="background:radial-gradient(120% 90% at 15% 10%,' . $p[0] . ' 0%,transparent 60%),radial-gradient(90% 80% at 90% 85%,' . $p[2] . ' 0%,transparent 65%),' . $p[1] . '"><span>' . e(mb_substr((string)$ev['title'], 0, 1)) . '</span></div>';
}
function site_page($title, $content, array $o = []) {
    $accent = setting('accent', '#a8665a'); $ig = setting('instagram');
    $desc = $o['desc'] ?? setting('tagline', 'Encuentros con encanto para disfrutar sin prisas.');
    $flash = '';
    foreach (take_flash() as $f) $flash .= '<div class="notice ' . e($f[0]) . '">' . e($f[1]) . '</div>';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . e($title ? $title . ' · ' . brand() : brand()) . '</title><meta name="description" content="' . e($desc) . '">'
        . '<meta property="og:title" content="' . e($title ?: brand()) . '"><meta property="og:description" content="' . e($desc) . '"><meta property="og:type" content="website">'
        . (!empty($o['image']) ? '<meta property="og:image" content="' . e($o['image']) . '">' : '')
        . (!empty($o['noindex']) ? '<meta name="robots" content="noindex">' : '')
        . '<link rel="stylesheet" href="' . asset('site.css') . '"><style>:root{--accent:' . e($accent) . '}</style></head><body>'
        . '<header class="nav"><a class="logo" href="' . e(url('')) . '">' . e(brand()) . '</a><nav><a href="' . e(url('')) . '#eventos">Próximos eventos</a><a href="' . e(url('')) . '#nosotras">Sobre nosotras</a>'
        . ($ig ? '<a href="' . e($ig) . '" target="_blank" rel="noopener">Instagram</a>' : '') . '</nav></header>'
        . '<main>' . $flash . $content . '</main>'
        . '<footer class="foot"><div class="logo">' . e(brand()) . '</div><p>' . e(setting('tagline', 'Encuentros con encanto para disfrutar sin prisas.')) . '</p><nav>'
        . ($ig ? '<a href="' . e($ig) . '" target="_blank" rel="noopener">Instagram</a>' : '')
        . (setting('contact_email') ? '<a href="mailto:' . e(setting('contact_email')) . '">Contacto</a>' : '')
        . '<a href="' . e(url('legal/condiciones')) . '">Condiciones</a><a href="' . e(url('legal/privacidad')) . '">Privacidad</a><a href="' . e(url('legal/aviso')) . '">Aviso legal</a></nav>'
        . '<small>© ' . date('Y') . ' ' . e(setting('legal_name', brand())) . '</small></footer>'
        . '<script src="' . asset('app.js') . '"></script>' . ($o['scripts'] ?? '') . '</body></html>';
}
