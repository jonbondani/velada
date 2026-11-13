<?php
need('users');
$roleHelp = '<div class="card panel"><h2>Qué puede hacer cada perfil</h2><table class="plain"><tbody>';
foreach (ROLES as $rk => $rl) {
    $mods = $rk === 'admin' ? ['Todo, incluidos usuarios y ajustes'] : array_map(fn($m, $p) => MODULES[$m] . ($p === 'r' ? ' (consulta)' : ''), array_keys(PERMS[$rk]), PERMS[$rk]);
    $roleHelp .= '<tr><td><b>' . e($rl) . '</b></td><td class="mute">' . e(implode(' · ', array_diff($mods, ['Inicio', 'Inicio (consulta)']))) . '</td></tr>';
}
$roleHelp .= '</tbody></table></div>';
$r = crud([
    'table' => 'users', 'route' => 'admin/users', 'perm' => 'users', 'singular' => 'usuario', 'new' => 'Añadir usuario', 'order' => 'active DESC, name', 'search' => ['name', 'email'],
    'fields' => [
        'name' => ['label' => 'Nombre', 'req' => 1, 'list' => 1],
        'email' => ['label' => 'Correo (para entrar)', 'type' => 'email', 'req' => 1, 'list' => 1, 'short' => 'Correo'],
        'role' => ['label' => 'Perfil', 'type' => 'select', 'req' => 1, 'options' => ROLES, 'list' => 1, 'default' => 'eventos'],
        'partner_id' => ['label' => 'Partner vinculado', 'type' => 'select', 'options' => fn() => pairs('SELECT id, name FROM partners ORDER BY name'), 'help' => 'Solo para el perfil Partner: verá únicamente sus eventos.'],
        'newpass' => ['label' => 'Contraseña', 'type' => 'static', 'render' => fn() => '<label class="fld"><span>Contraseña nueva</span><input type="password" name="newpass" minlength="8" autocomplete="new-password"><small>Mínimo 8 caracteres. Déjalo vacío para no cambiarla.</small></label>'],
        'active' => ['label' => 'Puede entrar al panel', 'type' => 'check', 'default' => 1, 'list' => 1, 'short' => 'Activo'],
    ],
    'cols' => ['Último acceso' => fn($r) => $r['last_login'] ? e(fdt($r['last_login'])) : '<span class="mute">Nunca</span>'],
    'validate' => function (array &$d, $rec) {
        $d['email'] = strtolower($d['email']);
        $np = (string)($_POST['newpass'] ?? '');
        if (!$rec && strlen($np) < 8) return 'Indica una contraseña de al menos 8 caracteres.';
        if ($np !== '' && strlen($np) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
        if ($np !== '') $d['pass'] = password_hash($np, PASSWORD_DEFAULT);
        if ($d['role'] === 'partner' && !$d['partner_id']) return 'Elige el partner vinculado a este usuario.';
        if ($rec && (int)$rec['id'] === (int)user()['id'] && ($d['role'] !== 'admin' || !$d['active'])) return 'No puedes quitarte a ti misma el perfil de administración ni desactivarte.';
        return null;
    },
    'before_delete' => function ($r) { if ((int)$r['id'] === (int)user()['id']) throw new RuntimeException('No puedes eliminar tu propio usuario.'); },
    'after_save' => fn($id, $d, $new) => log_activity($new ? 'Usuario creado' : 'Usuario modificado', $d['email']),
]);
admin_page('Usuarios', $r['html'] . ($r['mode'] === 'list' ? $roleHelp : ''), $r['actions'], $r['mode'] === 'form' ? url('admin/users') : '');
