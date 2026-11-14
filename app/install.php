<?php
// Instalador web. Solo funciona mientras no exista config/config.php
$base = PHP_SAPI === 'cli-server' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (substr($path, -12) === 'ping-rewrite') exit('pong');

// Ajusta RewriteBase a la carpeta real de instalacion (necesario en algunos alojamientos compartidos)
$ht = ROOT . '/.htaccess';
if (is_file($ht) && is_writable($ht)) {
    $c = file_get_contents($ht);
    $n = preg_replace('/^\s*#?\s*RewriteBase .*$/m', 'RewriteBase ' . $base . '/', $c);
    if ($n !== null && $n !== $c) file_put_contents($ht, $n);
}

$checks = [
    'PHP 8.0 o superior (tienes ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0', '>='),
    'Extensión PDO para MySQL o SQLite' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
    'Carpeta config con permiso de escritura' => is_writable(ROOT . '/config'),
    'Carpeta storage con permiso de escritura' => is_writable(ROOT . '/storage'),
    'Carpeta uploads con permiso de escritura' => is_writable(ROOT . '/uploads'),
];
$okAll = !in_array(false, $checks, true);
$err = ''; $p = $_POST + ['driver' => extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite', 'host' => 'localhost', 'port' => '3306', 'dbname' => '', 'dbuser' => '', 'brand' => '', 'name' => '', 'email' => '', 'demo' => '1', 'pretty' => '0'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $okAll) {
    try {
        if (mb_strlen(trim($p['brand'])) < 2) throw new RuntimeException('Indica el nombre de tu marca.');
        if (mb_strlen(trim($p['name'])) < 2) throw new RuntimeException('Indica tu nombre.');
        if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no parece correcto.');
        if (strlen($p['pass'] ?? '') < 8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        $db = $p['driver'] === 'sqlite'
            ? ['driver' => 'sqlite', 'file' => 'datos-' . bin2hex(random_bytes(6)) . '.sqlite']
            : ['driver' => 'mysql', 'host' => trim($p['host']), 'port' => (int)$p['port'] ?: 3306, 'name' => trim($p['dbname']), 'user' => trim($p['dbuser']), 'pass' => (string)($p['dbpass'] ?? '')];
        try { $pdo = db_connect($db); } catch (Throwable $e) { throw new RuntimeException('No se pudo conectar con la base de datos. Revisa servidor, nombre, usuario y contraseña. Detalle: ' . $e->getMessage()); }
        create_schema($pdo, $db['driver'] === 'mysql');
        $CONFIG = ['db' => $db, 'secret' => bin2hex(random_bytes(24)), 'timezone' => 'Europe/Madrid', 'pretty' => $p['pretty'] === '1', 'mail_off' => false];
        require ROOT . '/app/ui.php'; require ROOT . '/app/logic.php';
        if ((int)val('SELECT COUNT(*) FROM users') === 0) {
            insert('users', ['name' => trim($p['name']), 'email' => strtolower(trim($p['email'])), 'pass' => password_hash($p['pass'], PASSWORD_DEFAULT), 'role' => 'admin', 'active' => 1, 'is_super' => 1, 'tour_done' => 0, 'created_at' => now()]);
            set_setting('brand', trim($p['brand']));
            set_setting('contact_email', strtolower(trim($p['email'])));
            set_setting('schema_ver', APP_VER); set_setting('ai_enabled', '0'); set_setting('ai_roles', 'admin'); set_setting('pay_transfer', '1'); set_setting('pay_bizum', '1'); set_setting('tax_pct', '21'); set_setting('hold_hours', '72');
            if (!empty($p['demo'])) { require ROOT . '/app/seed.php'; seed_demo(); }
        }
        $code = "<?php\n// Configuracion generada por el instalador. No compartas este fichero.\nreturn " . var_export($CONFIG, true) . ";\n";
        if (file_put_contents(ROOT . '/config/config.php', $code) === false) throw new RuntimeException('No se pudo escribir config/config.php.');
        header('Location: ' . ($CONFIG['pretty'] ? $base . '/acceso' : $base . '/index.php?r=acceso'));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); $CONFIG = null; }
}
$v = fn($k) => htmlspecialchars((string)($p[$k] ?? ''), ENT_QUOTES);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalación</title>
<link rel="stylesheet" href="<?= $base ?>/assets/admin.css"></head><body class="solo">
<form method="post" class="card form install">
<h1>Instalación</h1><p class="intro">Tres pasos y lista. Podrás cambiarlo todo después desde Ajustes.</p>
<?php if ($err): ?><div class="flash err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<ul class="checks"><?php foreach ($checks as $l => $ok): ?><li class="<?= $ok ? 'ok' : 'ko' ?>"><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
<?php if (!$okAll): ?><div class="flash err">Corrige los puntos marcados en rojo y recarga esta página. Los permisos de carpeta se cambian desde el gestor de archivos o por SFTP (valor 755 o 775).</div><?php else: ?>
<h3>1. Base de datos</h3>
<div class="grid">
<label class="fld full"><span>Tipo</span><select name="driver" id="driver">
<?php if (extension_loaded('pdo_mysql')): ?><option value="mysql"<?= $p['driver'] === 'mysql' ? ' selected' : '' ?>>MySQL / MariaDB (recomendado, la que te da IONOS)</option><?php endif; ?>
<?php if (extension_loaded('pdo_sqlite')): ?><option value="sqlite"<?= $p['driver'] === 'sqlite' ? ' selected' : '' ?>>SQLite (sin configurar nada, para pruebas o poco volumen)</option><?php endif; ?>
</select></label>
<label class="fld my"><span>Servidor</span><input name="host" value="<?= $v('host') ?>" placeholder="db1234567.hosting-data.io"><small>En IONOS aparece como "Nombre de host" en la ficha de la base de datos.</small></label>
<label class="fld my"><span>Puerto</span><input name="port" value="<?= $v('port') ?>"></label>
<label class="fld my"><span>Nombre de la base de datos</span><input name="dbname" value="<?= $v('dbname') ?>" placeholder="dbs1234567"></label>
<label class="fld my"><span>Usuario</span><input name="dbuser" value="<?= $v('dbuser') ?>" placeholder="dbu1234567"></label>
<label class="fld my full"><span>Contraseña de la base de datos</span><input type="password" name="dbpass" autocomplete="off"></label>
</div>
<h3>2. Tu marca</h3>
<div class="grid"><label class="fld full"><span>Nombre comercial</span><input name="brand" value="<?= $v('brand') ?>" required placeholder="El nombre que verán tus clientas"></label></div>
<h3>3. Tu cuenta de administración</h3>
<div class="grid">
<label class="fld"><span>Tu nombre</span><input name="name" value="<?= $v('name') ?>" required></label>
<label class="fld"><span>Correo electrónico</span><input type="email" name="email" value="<?= $v('email') ?>" required></label>
<label class="fld full"><span>Contraseña (mínimo 8 caracteres)</span><input type="password" name="pass" minlength="8" required autocomplete="new-password"></label>
<label class="fld chk full"><input type="checkbox" name="demo" value="1"<?= !empty($p['demo']) ? ' checked' : '' ?>><span>Cargar datos de ejemplo para ver cómo funciona todo (se pueden borrar después desde Ajustes)</span></label>
</div>
<input type="hidden" name="pretty" id="pretty" value="0">
<div class="form-foot"><button class="btn primary" id="go">Instalar</button></div>
<?php endif; ?>
</form>
<script>
var d=document.getElementById('driver');function t(){document.querySelectorAll('.my').forEach(function(e){e.style.display=d&&d.value==='mysql'?'':'none'})}if(d){d.onchange=t;t()}
fetch('<?= $base ?>/ping-rewrite').then(function(r){return r.text()}).then(function(x){if(x==='pong')document.getElementById('pretty').value='1'}).catch(function(){});
</script></body></html>
