<?php
if ($seg[0] === 'salir') { $_SESSION = []; session_destroy(); redirect(url('acceso')); }
if (user()) redirect(url('admin'));
$err = '';
if (is_post()) {
    $ip = client_ip();
    q('DELETE FROM login_attempts WHERE created_at<?', [date('Y-m-d H:i:s', time() - 900)]);
    if ((int)val('SELECT COUNT(*) FROM login_attempts WHERE ip=?', [$ip]) >= 8) $err = 'Demasiados intentos seguidos. Espera 15 minutos y vuelve a probar.';
    else {
        $u = row('SELECT * FROM users WHERE email=? AND active=1', [strtolower(trim((string)($_POST['email'] ?? '')))]);
        if ($u && password_verify((string)($_POST['pass'] ?? ''), $u['pass'])) {
            session_regenerate_id(true); $_SESSION['uid'] = $u['id'];
            q('UPDATE users SET last_login=? WHERE id=?', [now(), $u['id']]);
            redirect(url($u['role'] === 'taquilla' ? 'admin/checkin' : 'admin'));
        }
        insert('login_attempts', ['ip' => $ip, 'created_at' => now()]);
        $err = 'El correo o la contraseña no son correctos.';
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Acceso · <?= e(brand()) ?></title><link rel="stylesheet" href="<?= asset('admin.css') ?>"></head><body class="solo">
<form method="post" class="card form login"><?= csrf_field() ?>
<div class="brand"><?= e(brand()) ?></div><p class="intro">Acceso al panel de gestión</p>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<label class="fld full"><span>Correo electrónico</span><input type="email" name="email" required autofocus autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label class="fld full"><span>Contraseña</span><input type="password" name="pass" required autocomplete="current-password"></label>
<button class="btn primary wide">Entrar</button>
<p class="mute small center">¿Has olvidado la contraseña? Pide a administración que te asigne una nueva.</p>
</form></body></html>
