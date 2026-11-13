<?php
need('customers');
if (($_GET['do'] ?? '') === 'csv') {
    csv_out('clientas', ['Nombre', 'Correo', 'Teléfono', 'Ciudad', 'Instagram', 'Acepta novedades', 'Reservas pagadas', 'Gasto total', 'Alta'],
        array_map(fn($r) => [$r['name'], $r['email'], $r['phone'], $r['city'], $r['instagram'], $r['marketing_ok'] ? 'Sí' : 'No', $r['n'], num($r['t'], 2), fdate($r['created_at'])],
            rows("SELECT c.id, c.name, c.email, c.phone, c.city, c.instagram, c.marketing_ok, c.created_at, COUNT(o.id) AS n, COALESCE(SUM(o.total),0) AS t FROM customers c LEFT JOIN orders o ON o.customer_id=c.id AND o.status='paid' GROUP BY c.id, c.name, c.email, c.phone, c.city, c.instagram, c.marketing_ok, c.created_at ORDER BY c.name")));
}
crud_page('Clientas', [
    'table' => 'customers', 'route' => 'admin/customers', 'perm' => 'customers', 'singular' => 'clienta', 'new' => 'Añadir clienta', 'order' => 'id DESC', 'search' => ['name', 'email', 'phone', 'city'],
    'empty' => 'Las clientas se crean solas con cada reserva o suscripción. También puedes añadirlas a mano.',
    'fields' => [
        'name' => ['label' => 'Nombre y apellidos', 'req' => 1, 'list' => 1, 'short' => 'Nombre'],
        'email' => ['label' => 'Correo', 'type' => 'email', 'req' => 1, 'list' => 1],
        'phone' => ['label' => 'Teléfono', 'type' => 'tel', 'list' => 1],
        'city' => ['label' => 'Ciudad'],
        'instagram' => ['label' => 'Instagram', 'ph' => '@usuario'],
        'marketing_ok' => ['label' => 'Acepta recibir novedades', 'type' => 'check', 'list' => 1, 'short' => 'Novedades'],
        'notes' => ['label' => 'Notas (gustos, alergias, fechas señaladas…)', 'type' => 'textarea'],
    ],
    'cols' => ['Reservas' => function ($r) { $x = row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE customer_id=? AND status='paid'", [$r['id']]); return $x['n'] ? '<span class="n">' . (int)$x['n'] . ' · ' . money($x['t']) . '</span>' : '<span class="mute">Ninguna</span>'; }],
    'validate' => function (array &$d, $rec) { $d['email'] = strtolower($d['email']); if (!$rec) $d['source'] = 'manual'; return null; },
    'before_delete' => function ($r) { if (val('SELECT COUNT(*) FROM orders WHERE customer_id=?', [$r['id']])) throw new RuntimeException('Esta clienta tiene reservas y no se puede eliminar. Si pide la baja de comunicaciones, desmarca "Acepta recibir novedades".'); },
    'form_extra' => function ($rec) {
        $os = rows('SELECT o.*, e.title FROM orders o JOIN events e ON e.id=o.event_id WHERE o.customer_id=? ORDER BY o.id DESC', [$rec['id']]);
        if (!$os) return panel('Historial', '<div class="nochart">Todavía no ha hecho ninguna reserva.</div>');
        $t = '<table class="plain"><tbody>';
        foreach ($os as $o) $t .= '<tr><td><a href="' . e(url('admin/orders', ['id' => $o['id']])) . '">' . e($o['num']) . '</a></td><td>' . e($o['title']) . '</td><td>' . fdate($o['created_at']) . '</td><td>' . badge(ORDER_STATUS[$o['status']], ORDER_BADGE[$o['status']]) . '</td><td class="r">' . money($o['total']) . '</td></tr>';
        return panel('Historial de reservas', $t . '</tbody></table>');
    },
], btn('Descargar', url('admin/customers', ['do' => 'csv'])));
