<?php
// Ajustes generales de la plataforma
need('settings');
$w = user()['role'] === 'admin';
$tabsAll = ['negocio' => 'Negocio', 'web' => 'Web pública', 'pagos' => 'Cobros', 'legal' => 'Textos legales', 'correos' => 'Correos enviados', 'actividad' => 'Actividad', 'datos' => 'Datos'];
$tab = isset($tabsAll[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'negocio';
$self = fn($x = []) => url('admin/settings', $x + ['tab' => $tab]);
$forms = [
    'negocio' => [
        'brand' => ['label' => 'Nombre comercial', 'req' => 1, 'help' => 'El que aparece en la web, los correos y el panel.'],
        'legal_name' => ['label' => 'Razón social o nombre fiscal'],
        'tax_id' => ['label' => 'NIF / CIF'],
        'phone' => ['label' => 'Teléfono de atención', 'type' => 'tel'],
        'address' => ['label' => 'Dirección fiscal', 'full' => 1],
        'contact_email' => ['label' => 'Correo de contacto', 'type' => 'email', 'help' => 'Donde responden las clientas.'],
        'mail_from' => ['label' => 'Correo remitente', 'type' => 'email', 'help' => 'Debe ser una dirección de tu propio dominio creada en IONOS, por ejemplo hola@tudominio.com. Si no, los correos pueden acabar en spam.'],
    ],
    'web' => [
        'tagline' => ['label' => 'Frase de marca', 'full' => 1, 'ph' => 'Encuentros con encanto para disfrutar sin prisas.'],
        'hero_title' => ['label' => 'Titular de portada', 'full' => 1, 'ph' => 'Momentos que apetece repetir'],
        'hero_text' => ['label' => 'Texto bajo el titular', 'type' => 'textarea', 'rows' => 3],
        'about_title' => ['label' => 'Título de "Sobre nosotras"', 'full' => 1],
        'about_text' => ['label' => 'Texto de "Sobre nosotras"', 'type' => 'textarea', 'rows' => 6],
        'instagram' => ['label' => 'Enlace a tu Instagram', 'type' => 'url', 'ph' => 'https://www.instagram.com/tumarca'],
        'accent' => ['label' => 'Color de marca', 'type' => 'color', 'default' => '#a8665a'],
    ],
    'pagos' => [
        'tax_pct' => ['label' => 'IVA habitual de las entradas', 'type' => 'pct', 'default' => 21, 'help' => 'Se propone al crear cada evento. Consulta con tu asesoría qué tipo corresponde a tu actividad.'],
        'hold_hours' => ['label' => 'Horas que se guarda una reserva sin pagar', 'type' => 'number', 'default' => 72, 'help' => 'Pasado ese tiempo las plazas se liberan solas.'],
        'pay_transfer' => ['label' => 'Aceptar transferencia bancaria', 'type' => 'check', 'section' => 'Transferencia'],
        'iban' => ['label' => 'IBAN donde recibir las transferencias'],
        'pay_bizum' => ['label' => 'Aceptar Bizum', 'type' => 'check', 'section' => 'Bizum'],
        'bizum' => ['label' => 'Teléfono de Bizum', 'type' => 'tel'],
        'stripe_secret' => ['label' => 'Clave secreta de Stripe', 'type' => 'password', 'section' => 'Tarjeta con Stripe', 'help' => 'Empieza por sk_live_ o sk_test_. Con ella se activa el pago con tarjeta, Apple Pay y Google Pay.'],
        'stripe_whsec' => ['label' => 'Secreto del webhook de Stripe', 'type' => 'password', 'help' => 'Empieza por whsec_. Dirección del webhook: ' . abs_url('stripe/webhook') . ' (evento checkout.session.completed).'],
    ],
    'legal' => [
        'legal_condiciones' => ['label' => 'Condiciones de compra', 'type' => 'textarea', 'rows' => 10, 'help' => 'Si lo dejas vacío se muestra un texto base. Revísalo con tu asesoría antes de vender.'],
        'legal_privacidad' => ['label' => 'Política de privacidad', 'type' => 'textarea', 'rows' => 10],
        'legal_aviso' => ['label' => 'Aviso legal', 'type' => 'textarea', 'rows' => 10],
    ],
];
if (is_post()) {
    if (!$w) need('users', 'w');
    if (($_GET['act'] ?? '') === 'wipe' && ($_POST['confirm'] ?? '') === 'BORRAR') {
        foreach (array_keys(schema()) as $t) if (!in_array($t, ['settings', 'users', 'login_attempts'], true)) q("DELETE FROM $t");
        q("UPDATE users SET partner_id=NULL WHERE role<>'partner'"); q("DELETE FROM users WHERE id<>? AND (role='partner' OR email LIKE '%@ejemplo.test')", [user()['id']]);
        foreach (glob(ROOT . '/storage/files/*') ?: [] as $f) @unlink($f);
        log_activity('Datos borrados', 'Se han vaciado eventos, ventas, contactos y finanzas'); flash('Datos borrados. La plataforma está limpia para empezar.');
        redirect($self());
    }
    if (isset($forms[$tab])) {
        foreach ($forms[$tab] as $k => $f) {
            $ty = $f['type'] ?? 'text';
            if ($ty === 'check') { set_setting($k, isset($_POST[$k]) ? '1' : '0'); continue; }
            $v = trim((string)($_POST[$k] ?? ''));
            if ($ty === 'password' && $v === '') continue;
            if ($ty === 'pct' || $ty === 'money') $v = (string)round((float)str_replace(',', '.', $v), 2);
            if ($ty === 'number') $v = (string)(int)$v;
            if ($ty === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) $v = '#a8665a';
            if (!empty($f['req']) && $v === '') { flash('Falta completar "' . $f['label'] . '".', 'err'); redirect($self()); }
            set_setting($k, $v);
        }
        if ($tab === 'pagos' && !empty($_POST['stripe_clear'])) { set_setting('stripe_secret', ''); set_setting('stripe_whsec', ''); }
        log_activity('Ajustes modificados', $tabsAll[$tab]); flash('Ajustes guardados.');
    }
    redirect($self());
}
$h = '';
if (isset($forms[$tab])) {
    $h .= '<form method="post" class="card form" action="' . e($self()) . '">' . csrf_field() . '<fieldset' . ($w ? '' : ' disabled') . '><div class="grid">';
    foreach ($forms[$tab] as $k => $f) {
        if (isset($f['section'])) $h .= '<h3 class="full">' . e($f['section']) . '</h3>';
        $h .= render_field($k, $f, setting($k, $f['default'] ?? ''));
    }
    if ($tab === 'pagos') $h .= '<label class="fld chk full"><input type="checkbox" name="stripe_clear" value="1"><span>Desconectar Stripe (borra las claves guardadas)</span></label>'
        . '<p class="full mute small">Estado del pago con tarjeta: ' . (stripe_on() ? badge('Activo', 'green') : badge('Sin configurar')) . '. Mientras no esté activo, la web ofrece solo los medios que marques arriba.</p>';
    $h .= '</div></fieldset>' . ($w ? '<div class="form-foot"><button class="btn primary">Guardar</button></div>' : '<p class="mute">Solo administración puede cambiar los ajustes.</p>') . '</form>';
} elseif ($tab === 'correos') {
    $list = rows('SELECT * FROM emails ORDER BY id DESC LIMIT 100');
    if (!$list) $h .= '<div class="empty"><b>Todavía no se ha enviado ningún correo.</b></div>';
    else { $h .= '<div class="card table"><table><thead><tr><th>Fecha</th><th>Para</th><th>Asunto</th><th>Envío</th></tr></thead><tbody>';
        foreach ($list as $r) $h .= '<tr><td>' . fdt($r['created_at']) . '</td><td>' . e($r['to_email']) . '</td><td>' . e($r['subject']) . '</td><td>' . ($r['sent'] ? badge('Entregado al servidor', 'green') : badge('No enviado', 'red')) . '</td></tr>';
        $h .= '</tbody></table></div><p class="mute small">Si ves correos "No enviado", revisa en Negocio que el correo remitente sea una dirección real de tu dominio.</p>'; }
} elseif ($tab === 'actividad') {
    $list = rows('SELECT a.*, u.name FROM activity a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200');
    if (!$list) $h .= '<div class="empty"><b>Sin actividad registrada todavía.</b></div>';
    else { $h .= '<div class="card table"><table><thead><tr><th>Fecha</th><th>Quién</th><th>Qué</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($list as $r) $h .= '<tr><td>' . fdt($r['created_at']) . '</td><td>' . e($r['name'] ?: 'Sistema') . '</td><td>' . e($r['action']) . '</td><td class="mute">' . e($r['detail']) . '</td></tr>';
        $h .= '</tbody></table></div>'; }
} elseif ($tab === 'datos') {
    $h .= panel('Empezar de cero', '<p>Borra todos los eventos, reservas, clientas, proveedores, facturas, publicaciones y estadísticas. Se conservan los ajustes y los usuarios del equipo. Útil para eliminar los datos de ejemplo antes de empezar a trabajar de verdad.</p>'
        . ($w ? '<form method="post" action="' . e($self(['act' => 'wipe'])) . '" class="payform">' . csrf_field() . '<label class="fld"><span>Escribe BORRAR para confirmar</span><input name="confirm" autocomplete="off" required pattern="BORRAR"></label><button class="btn danger" data-confirm="Esta acción no se puede deshacer. ¿Borrar todos los datos?">Borrar todos los datos</button></form>' : '<p class="mute">Solo administración puede hacerlo.</p>'))
        . panel('Copia de seguridad', '<p>Los datos viven en la base de datos de tu alojamiento. Desde el panel de IONOS puedes descargar una copia (phpMyAdmin, opción Exportar) y conviene hacerlo de forma periódica. Los documentos adjuntos están en la carpeta <code>storage/files</code> y las fotos en <code>uploads</code>.</p><p class="mute">Versión ' . APP_VER . ' · PHP ' . PHP_VERSION . ' · ' . (is_mysql() ? 'MySQL' : 'SQLite') . '</p>');
}
admin_page('Ajustes', tabs('admin/settings', [], $tabsAll, $tab) . $h);
