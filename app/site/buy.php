<?php
if (!is_post()) redirect(url(''));
$ev = row('SELECT * FROM events WHERE id=?', [(int)($_POST['event_id'] ?? 0)]);
if (!$ev) not_found();
$back = url('evento/' . $ev['slug']) . '#reservar';
if (!empty($_POST['website'])) redirect($back);
$_SESSION['old'] = array_intersect_key($_POST, array_flip(['name', 'email', 'phone', 'code', 'method', 'qty', 'marketing_ok', 'invoice_name', 'invoice_tax_id', 'invoice_address']));
$str = fn($k) => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
try {
    if (empty($_POST['terms'])) throw new RuntimeException('Para reservar necesitamos que aceptes las condiciones.');
    $methods = public_methods(); $m = $str('method');
    if (!isset($methods[$m])) $m = array_key_first($methods) ?? 'invite';
    $qty = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
    if ($m === 'invite') {
        $paidSel = 0; foreach ($qty as $tid => $n) if ((int)$n > 0) $paidSel += (float)val('SELECT price FROM ticket_types WHERE id=?', [(int)$tid]);
        if ($paidSel > 0) throw new RuntimeException('Ahora mismo no podemos aceptar reservas por la web. Escríbenos y te ayudamos encantadas.');
    }
    $o = create_order($ev, $qty, ['name' => $str('name'), 'email' => $str('email'), 'phone' => $str('phone'), 'marketing_ok' => !empty($_POST['marketing_ok'])], $m, $str('code'),
        ['utm' => $_SESSION['utm'] ?? [], 'invoice_name' => $str('invoice_name'), 'invoice_tax_id' => $str('invoice_tax_id'), 'invoice_address' => $str('invoice_address')]);
    unset($_SESSION['old']);
    if ($o['status'] === 'pending' && $m === 'stripe') {
        try { $go = stripe_checkout_url($o); }
        catch (RuntimeException $e) { cancel_order($o['id']); throw new RuntimeException('No hemos podido iniciar el pago con tarjeta. Inténtalo de nuevo en unos minutos o elige otra forma de pago.'); }
        redirect($go);
    }
    redirect(url('pedido/' . $o['token']));
} catch (RuntimeException $e) { flash($e->getMessage(), 'err'); redirect($back); }
