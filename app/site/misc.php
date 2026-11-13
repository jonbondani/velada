<?php
// Suscripcion a novedades, baja y textos legales
if ($seg[0] === 'suscribir') {
    if (is_post() && empty($_POST['website'])) {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($_POST['ok'])) flash('Revisa el correo y marca la casilla para poder avisarte.', 'err');
        else {
            $c = row('SELECT id FROM customers WHERE email=?', [$email]);
            if ($c) update('customers', ['marketing_ok' => 1], $c['id']); else insert('customers', ['email' => $email, 'name' => '', 'marketing_ok' => 1, 'source' => 'newsletter', 'created_at' => now()]);
            flash('Hecho. Te avisaremos de las próximas fechas.');
        }
    }
    redirect(url(''));
}
if ($seg[0] === 'baja') {
    $email = strtolower(trim((string)($_GET['e'] ?? '')));
    if ($email !== '' && hash_equals(sign($email), (string)($_GET['h'] ?? ''))) q('UPDATE customers SET marketing_ok=0 WHERE email=?', [$email]);
    site_page('Baja', '<section class="wrap narrow center pad"><h1>Listo</h1><p class="lead">No volverás a recibir novedades nuestras. Si cambias de idea, aquí estaremos.</p></section>', ['noindex' => true]); return;
}
$name = setting('legal_name', brand()); $mail = setting('contact_email', 'nuestro correo de contacto');
$docs = [
    'condiciones' => ['Condiciones de compra', "Estas condiciones regulan la reserva de plazas en los eventos de $name.\n\nReserva y pago. La reserva queda confirmada al recibir el pago. Si se elige transferencia o Bizum, las plazas se guardan durante el plazo indicado en la propia reserva; pasado ese tiempo sin recibir el pago, se liberan.\n\nEntradas. Tras el pago recibirás por correo un enlace a tus entradas con código QR, que se validan en el acceso. Cada entrada permite un único acceso.\n\nCancelaciones. Al tratarse de actividades de ocio con fecha concreta, no se aplica el derecho de desistimiento de 14 días. Si no puedes asistir, escríbenos con antelación y buscaremos la mejor solución, como ceder tu plaza a otra persona.\n\nCambios y cancelación por nuestra parte. Si un evento se cancela, se devolverá el importe íntegro por el mismo medio de pago. Si cambia de fecha, podrás elegir entre mantener la reserva o recibir la devolución.\n\nAlergias y necesidades especiales. Indícanoslas al reservar o escribiendo a $mail para poder atenderte bien.\n\nContacto: $mail."],
    'privacidad' => ['Política de privacidad', "Responsable: $name. Contacto: $mail.\n\nQué datos tratamos. Nombre, correo electrónico, teléfono y, si pides factura, tus datos fiscales. También los datos de tus reservas.\n\nPara qué. Para gestionar tu reserva, enviarte tus entradas y avisos sobre el evento, emitir la factura y, solo si lo has aceptado, informarte de próximos eventos.\n\nBase legal. La ejecución del contrato de reserva, el cumplimiento de obligaciones legales y tu consentimiento para las comunicaciones comerciales, que puedes retirar cuando quieras.\n\nConservación. Mientras dure la relación y durante los plazos legales aplicables.\n\nDestinatarios. No cedemos tus datos a terceros salvo obligación legal. Usamos proveedores necesarios para prestar el servicio, como el alojamiento web y, en su caso, la pasarela de pago.\n\nTus derechos. Puedes pedir el acceso, rectificación, supresión, oposición, limitación y portabilidad escribiendo a $mail. También puedes reclamar ante la Agencia Española de Protección de Datos.\n\nMedición de visitas. Esta web cuenta las visitas de forma agregada y sin cookies de seguimiento. Solo se usa una cookie técnica necesaria para completar la reserva."],
    'aviso' => ['Aviso legal', "Titular de la web: $name" . (setting('tax_id') ? ', con NIF ' . setting('tax_id') : '') . (setting('address') ? ' y domicilio en ' . setting('address') : '') . ".\n\nContacto: $mail.\n\nEsta web ofrece información y venta de plazas para eventos organizados por el titular. Los contenidos, textos e imágenes son propiedad del titular o se usan con permiso, y no pueden reproducirse sin autorización.\n\nEl titular no se hace responsable del mal uso de la web ni de los contenidos de sitios de terceros enlazados."],
];
$k = $seg[1] ?? ''; if (!isset($docs[$k])) not_found();
$text = setting('legal_' . $k, $docs[$k][1]);
site_page($docs[$k][0], '<section class="wrap narrow legal"><h1>' . e($docs[$k][0]) . '</h1><div class="prose">' . nl2br(e($text)) . '</div></section>');
