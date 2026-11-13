<?php
// Nucleo: configuracion, base de datos, sesion, permisos y utilidades
const APP_VER = '1.1.0';
define('ROOT', dirname(__DIR__));
$CONFIG = is_file(ROOT.'/config/config.php') ? require ROOT.'/config/config.php' : null;
date_default_timezone_set(is_array($CONFIG) ? ($CONFIG['timezone'] ?? 'Europe/Madrid') : 'Europe/Madrid');
mb_internal_encoding('UTF-8');

function cfg($k, $d = null) { global $CONFIG; return is_array($CONFIG) && array_key_exists($k, $CONFIG) ? $CONFIG[$k] : $d; }

/* ---------- Base de datos ---------- */
function db_connect(array $c) {
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . ROOT . '/storage/' . $c['file']);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
    } else {
        $dsn = 'mysql:host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function db() { static $pdo; if (!$pdo) $pdo = db_connect(cfg('db')); return $pdo; }
function is_mysql() { return cfg('db')['driver'] !== 'sqlite'; }
function q($sql, $p = []) { $s = db()->prepare($sql); $s->execute(array_values($p)); return $s; }
function rows($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function row($sql, $p = []) { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val($sql, $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function pairs($sql, $p = []) { return q($sql, $p)->fetchAll(PDO::FETCH_KEY_PAIR); }
function schema() { static $S; if (!$S) $S = require ROOT . '/app/schema.php'; return $S; }

function clean_row($t, array $d) {
    $types = schema()[$t]['cols'] ?? [];
    foreach ($d as $k => $v) {
        if (!isset($types[$k])) { unset($d[$k]); continue; }
        if (is_string($v)) $v = trim($v);
        switch ($types[$k]) {
            case 'int': case 'bool': $v = (int)$v; break;
            case 'money': $v = round((float)str_replace(',', '.', (string)$v), 2); break;
            case 'ref': $v = ((int)$v > 0) ? (int)$v : null; break;
            case 'dt': $ts = $v ? strtotime(str_replace('T', ' ', (string)$v)) : false; $v = $ts ? date('Y-m-d H:i:s', $ts) : null; break;
            case 'date': $ts = $v ? strtotime((string)$v) : false; $v = $ts ? date('Y-m-d', $ts) : null; break;
            case 'text': $v = (string)$v; break;
            default: $v = mb_substr((string)$v, 0, 255);
        }
        $d[$k] = $v;
    }
    return $d;
}
function insert($t, array $d) {
    $d = clean_row($t, $d);
    q("INSERT INTO $t (" . implode(',', array_keys($d)) . ') VALUES (' . implode(',', array_fill(0, count($d), '?')) . ')', $d);
    return (int)db()->lastInsertId();
}
function update($t, array $d, $id) {
    $d = clean_row($t, $d);
    if (!$d) return;
    $set = implode(',', array_map(fn($k) => "$k=?", array_keys($d)));
    q("UPDATE $t SET $set WHERE id=?", array_merge(array_values($d), [(int)$id]));
}
function col_types($mysql) {
    return $mysql
        ? ['pk'=>'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY','key'=>'VARCHAR(100) NOT NULL PRIMARY KEY','str'=>"VARCHAR(255) NOT NULL DEFAULT ''",'text'=>'MEDIUMTEXT NULL','int'=>'INT NOT NULL DEFAULT 0','ref'=>'INT UNSIGNED NULL','money'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','dt'=>'DATETIME NULL','date'=>'DATE NULL','bool'=>'TINYINT NOT NULL DEFAULT 0']
        : ['pk'=>'INTEGER PRIMARY KEY AUTOINCREMENT','key'=>'TEXT NOT NULL PRIMARY KEY','str'=>"TEXT NOT NULL DEFAULT ''",'text'=>'TEXT NULL','int'=>'INTEGER NOT NULL DEFAULT 0','ref'=>'INTEGER NULL','money'=>'REAL NOT NULL DEFAULT 0','dt'=>'TEXT NULL','date'=>'TEXT NULL','bool'=>'INTEGER NOT NULL DEFAULT 0'];
}
// Al actualizar la app a una version nueva, crea las tablas y columnas que falten sin tocar los datos
function migrate() {
    if (setting('schema_ver') === APP_VER) return;
    $pdo = db(); $mysql = is_mysql(); $map = col_types($mysql);
    create_schema($pdo, $mysql);
    foreach (schema() as $t => $def) {
        $have = $mysql ? array_column(rows("SHOW COLUMNS FROM $t"), 'Field') : array_column(rows("PRAGMA table_info($t)"), 'name');
        foreach ($def['cols'] as $c => $ty) if (!in_array($c, $have, true) && $ty !== 'pk' && $ty !== 'key') $pdo->exec("ALTER TABLE $t ADD COLUMN $c {$map[$ty]}");
    }
    set_setting('schema_ver', APP_VER);
}
function create_schema(PDO $pdo, $mysql) {
    $map = col_types($mysql);
    foreach (require ROOT . '/app/schema.php' as $t => $def) {
        $cols = [];
        foreach ($def['cols'] as $c => $ty) $cols[] = "$c {$map[$ty]}";
        $pdo->exec("CREATE TABLE IF NOT EXISTS $t (" . implode(', ', $cols) . ')' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
        foreach (['uniq' => 'UNIQUE INDEX', 'idx' => 'INDEX'] as $k => $kw) {
            foreach ($def[$k] ?? [] as $c) {
                try { $pdo->exec("CREATE $kw {$t}_{$c} ON $t ($c)"); } catch (Throwable $e) { /* ya existe */ }
            }
        }
    }
}

/* ---------- Sesion, CSRF, usuarios ---------- */
function is_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }
function start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('velada');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
}
function csrf() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function check_csrf() {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($t) || !hash_equals(csrf(), $t)) { http_response_code(403); exit('La sesión ha caducado. Vuelve atrás, recarga la página e inténtalo de nuevo.'); }
}
function is_post() { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function user() {
    static $u = false;
    if ($u === false) { $u = empty($_SESSION['uid']) ? null : row('SELECT * FROM users WHERE id=? AND active=1', [$_SESSION['uid']]); }
    return $u;
}

const ROLES = [
    'admin' => 'Administración', 'direccion' => 'Dirección', 'eventos' => 'Gestión de eventos',
    'finanzas' => 'Finanzas', 'marketing' => 'Marketing y redes', 'operaciones' => 'Proveedores y montaje',
    'taquilla' => 'Taquilla y accesos', 'partner' => 'Partner (externo)',
];
const MODULES = [
    'dashboard' => 'Inicio', 'events' => 'Eventos', 'orders' => 'Ventas', 'customers' => 'Clientas', 'checkin' => 'Accesos QR',
    'partners' => 'Partners', 'venues' => 'Locales', 'suppliers' => 'Proveedores y personal', 'finance' => 'Finanzas',
    'marketing' => 'Marketing y redes', 'stats' => 'Estadísticas', 'users' => 'Usuarios', 'settings' => 'Ajustes',
];
// w = gestiona, r = solo consulta, ausente = sin acceso
const PERMS = [
    'direccion'   => ['dashboard'=>'w','events'=>'w','orders'=>'w','customers'=>'w','checkin'=>'w','partners'=>'w','venues'=>'w','suppliers'=>'w','finance'=>'w','marketing'=>'w','stats'=>'r','settings'=>'r'],
    'eventos'     => ['dashboard'=>'r','events'=>'w','orders'=>'w','customers'=>'w','checkin'=>'w','partners'=>'r','venues'=>'w','suppliers'=>'r','marketing'=>'r','stats'=>'r'],
    'finanzas'    => ['dashboard'=>'r','events'=>'r','orders'=>'w','customers'=>'r','partners'=>'w','venues'=>'r','suppliers'=>'w','finance'=>'w','stats'=>'r'],
    'marketing'   => ['dashboard'=>'r','events'=>'r','orders'=>'r','customers'=>'r','marketing'=>'w','stats'=>'r'],
    'operaciones' => ['dashboard'=>'r','events'=>'w','venues'=>'w','suppliers'=>'w','partners'=>'r'],
    'taquilla'    => ['dashboard'=>'r','events'=>'r','orders'=>'r','checkin'=>'w'],
    'partner'     => ['dashboard'=>'r','events'=>'r'],
];
function can($m, $lvl = 'r') {
    $u = user(); if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    $p = PERMS[$u['role']][$m] ?? '';
    return $lvl === 'r' ? $p !== '' : $p === 'w';
}
function need($m, $lvl = 'r') {
    if (!user()) redirect(url('acceso'));
    if (!can($m, $lvl)) {
        http_response_code(403);
        admin_page('Sin acceso', '<div class="empty"><b>Tu perfil no tiene permiso para esto.</b><br>Si lo necesitas, pide a administración que revise tu perfil.</div>');
        exit;
    }
}
function log_activity($action, $detail = '') {
    try { insert('activity', ['user_id' => user()['id'] ?? null, 'action' => $action, 'detail' => $detail, 'created_at' => now()]); } catch (Throwable $e) {}
}

/* ---------- Utilidades ---------- */
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function now() { return date('Y-m-d H:i:s'); }
function base_path() {
    static $b = null;
    if ($b === null) $b = PHP_SAPI === 'cli-server' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $b;
}
function url($r = '', array $p = []) {
    if (cfg('pretty', true)) { $u = base_path() . '/' . $r; }
    else { $u = base_path() . '/index.php'; if ($r !== '') $p = ['r' => $r] + $p; }
    $p = array_filter($p, fn($v) => $v !== null && $v !== '');
    return $p ? $u . '?' . http_build_query($p) : $u;
}
function abs_url($r = '', array $p = []) { return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($r, $p); }
function asset($f) { return base_path() . '/assets/' . $f . '?v=' . APP_VER; }
function redirect($u) { header('Location: ' . $u); exit; }
function flash($msg, $type = 'ok') { $_SESSION['flash'][] = [$type, $msg]; }
function take_flash() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function money($v) { return number_format((float)$v, 2, ',', '.') . ' €'; }
function num($v, $d = 0) { return number_format((float)$v, $d, ',', '.'); }
function fdate($s, $f = 'd/m/Y') { if (!$s) return ''; $t = strtotime((string)$s); return $t ? date($f, $t) : ''; }
function fdt($s) { return fdate($s, 'd/m/Y H:i'); }
const MESES = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
const DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
function fecha_larga($s, $hora = false) {
    $t = strtotime((string)$s); if (!$t) return '';
    $r = DIAS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' de ' . MESES[(int)date('n', $t)];
    if (date('Y', $t) !== date('Y')) $r .= ' de ' . date('Y', $t);
    return $hora ? $r . ' · ' . date('H:i', $t) . ' h' : $r;
}
function slugify($s) {
    $s = mb_strtolower(trim((string)$s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','à'=>'a','è'=>'e','ò'=>'o']);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return $s !== '' ? $s : 'evento';
}
function setting($k, $d = '') {
    static $S = null;
    if ($k === null) { $S = null; return null; }
    if ($S === null) { $S = []; foreach (rows('SELECT k, v FROM settings') as $r) $S[$r['k']] = $r['v']; }
    return (isset($S[$k]) && $S[$k] !== '' && $S[$k] !== null) ? $S[$k] : $d;
}
function set_setting($k, $v) {
    if (val('SELECT COUNT(*) FROM settings WHERE k=?', [$k])) q('UPDATE settings SET v=? WHERE k=?', [(string)$v, $k]);
    else q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, (string)$v]);
    setting(null);
}
function brand() { return setting('brand', 'Velada'); }
function token($bytes = 16) { return bin2hex(random_bytes($bytes)); }
function sign($s) { return substr(hash_hmac('sha256', (string)$s, (string)cfg('secret', 'x')), 0, 32); }
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function csv_out($name, array $head, array $data) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $head, ';', '"', '');
    foreach ($data as $r) {
        // evita que Excel interprete texto como formula
        $r = array_map(fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], '=+@-') !== false && !is_numeric($v)) ? "'" . $v : $v, array_values($r));
        fputcsv($o, $r, ';', '"', '');
    }
    exit;
}
// Sube un fichero validando tipo real. Devuelve nombre guardado, '' si no hay fichero, o lanza excepcion.
function upload($field, $dir, array $allowed) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el fichero (puede que sea demasiado grande).');
    if ($f['size'] > 8 * 1024 * 1024) throw new RuntimeException('El fichero supera los 8 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (!isset($types[$mime]) || !in_array($types[$mime], $allowed, true)) throw new RuntimeException('Formato no admitido. Permitidos: ' . implode(', ', $allowed) . '.');
    $name = token(12) . '.' . $types[$mime];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('No se pudo guardar el fichero. Revisa los permisos de la carpeta.');
    return $name;
}
