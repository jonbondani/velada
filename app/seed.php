<?php
// Datos de ejemplo para ver la plataforma en marcha. Se borran desde Ajustes > Datos.
function seed_demo() {
    $GLOBALS['NO_MAIL'] = true; mt_srand(11);
    $day = fn($n, $time = '00:00:00') => date('Y-m-d', time() + $n * 86400) . ' ' . $time;
    $pick = function (array $w) { $r = mt_rand(1, array_sum($w)); foreach ($w as $k => $v) { if (($r -= $v) <= 0) return $k; } return array_key_first($w); };
    $admin = (int)val('SELECT id FROM users ORDER BY id LIMIT 1');

    $venue = [];
    foreach ([['Finca La Alborada', 'Camino de los Olivos 12', 'Marbella', 80, 1200, 'Jardín, salón interior de 120 m2, cocina de apoyo, aparcamiento para 40 coches. Carga y descarga por el acceso lateral hasta las 17:00.'],
        ['Ático Mirador', 'Calle Larios 8, ático', 'Málaga', 40, 650, 'Terraza con vistas, barra, 6 mesas altas, equipo de sonido. Ascensor de servicio.'],
        ['Casa Jazmín', 'Plaza de los Naranjos 3', 'Marbella', 24, 380, 'Patio andaluz cubierto, mesa imperial para 20, menaje básico incluido.']] as $v)
        $venue[] = insert('venues', ['name' => $v[0], 'address' => $v[1], 'city' => $v[2], 'capacity' => $v[3], 'rental_cost' => $v[4], 'features' => $v[5], 'contact' => 'Recepción', 'phone' => '600 000 000', 'active' => 1, 'created_at' => now()]);
    $partner = [];
    foreach ([['Lucía Herrera', 'Chef de cocina de temporada', 55, '@luciaherrera.chef'], ['Atelier Flor de Sal', 'Taller de arte floral', 60, '@atelierflordesal'], ['Bodega Tres Lunas', 'Sumiller y catas de autor', 50, '@bodegatreslunas']] as $p)
        $partner[] = insert('partners', ['name' => $p[0], 'speciality' => $p[1], 'share_pct' => $p[2], 'instagram' => $p[3], 'contact' => explode(' ', $p[0])[0], 'email' => 'partner' . (count($partner) + 1) . '@ejemplo.test', 'active' => 1, 'created_at' => now()]);
    $sup = [];
    foreach ([['Mobiliario Azahar', 'empresa', 'Mobiliario y menaje', 'Presupuesto por evento'], ['Lino y Mesa', 'empresa', 'Mantelería y textil', '4 € por mantel'], ['Flores del Sur', 'empresa', 'Floristería', 'Presupuesto por evento'],
        ['Sonido Brisa', 'empresa', 'Iluminación y sonido', '280 € por evento'], ['Marta Gil', 'persona', 'Fotografía y vídeo', '250 € por evento'], ['Carlos Ruiz', 'persona', 'Personal de sala', '15 € la hora'], ['Elena Soto', 'persona', 'Personal de sala', '15 € la hora']] as $s)
        $sup[] = insert('suppliers', ['name' => $s[0], 'kind' => $s[1], 'category' => $s[2], 'rate' => $s[3], 'phone' => '600 000 000', 'active' => 1, 'created_at' => now()]);
    foreach (['direccion' => 'Dirección (ejemplo)', 'eventos' => 'Gestora de eventos (ejemplo)', 'finanzas' => 'Finanzas (ejemplo)', 'marketing' => 'Marketing (ejemplo)', 'operaciones' => 'Montaje (ejemplo)', 'taquilla' => 'Taquilla (ejemplo)', 'partner' => 'Lucía Herrera (ejemplo)'] as $role => $name)
        insert('users', ['name' => $name, 'email' => $role . '@ejemplo.test', 'pass' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), 'role' => $role, 'partner_id' => $role === 'partner' ? $partner[0] : null, 'active' => 0, 'created_at' => now()]);

    // [titulo, frase, tipo, dias desde hoy, hora, local, partner, aforo, estado, [entradas], vendidas, descripcion, incluye]
    $events = [
        ['Taller de arreglos florales de otoño', 'Una tarde entre flores, té y buena conversación', 'Taller', -44, '17:30:00', 2, 1, 16, 'done', [['Plaza en el taller', 68, 16]], 16,
            "Aprende a componer un centro de mesa con flor de temporada de la mano de Atelier Flor de Sal. Grupo reducido, materiales incluidos y merienda en el patio.", "Todos los materiales y flores\nTu centro de mesa para llevar\nMerienda con té y dulces artesanos"],
        ['Cena a la luz de las velas', 'Menú de otoño en cinco pases en una finca entre olivos', 'Cena', -20, '20:30:00', 0, 0, 40, 'done', [['Cubierto', 95, 0, 1], ['Mesa para dos', 180, 8, 2]], 38,
            "Una mesa larga en el jardín, cientos de velas y la cocina de temporada de Lucía Herrera. Una noche para vestirse bonito y dejarse llevar.", "Cóctel de bienvenida\nMenú degustación de cinco pases\nMaridaje de vinos\nMúsica en directo"],
        ['Brunch entre amigas', 'Domingo sin prisas en la terraza con mejores vistas de la ciudad', 'Brunch', 9, '12:00:00', 1, 0, 30, 'published', [['Entrada', 48, 0, 1], ['Grupo de cuatro', 172, 4, 4]], 22,
            "Mesa dulce y salada, mimosas y la mejor luz del mediodía. Ven con tus amigas o ven sola y vuelve acompañada.", "Brunch completo dulce y salado\nMimosas y café de especialidad\nDetalle de bienvenida"],
        ['Cata de vinos y quesos de autor', 'Seis vinos, seis quesos y un sumiller que lo cuenta todo', 'Cata', 23, '19:30:00', 2, 2, 24, 'published', [['Plaza en la cata', 62, 0]], 9,
            "Un recorrido por pequeñas bodegas andaluzas maridado con quesos artesanos, en el patio de Casa Jazmín. Para aprender disfrutando, sin tecnicismos.", "Cata guiada de seis vinos\nTabla de quesos artesanos\nCuaderno de cata"],
        ['Cena de Navidad en la finca', 'La gran noche del año, con mesa imperial y sobremesa larga', 'Cena', 36, '20:30:00', 0, 0, 60, 'published', [['Cubierto', 120, 0, 1], ['Mesa completa de ocho', 880, 3, 8]], 14,
            "Despedimos el año como nos gusta: buena mesa, buena compañía y todos los detalles cuidados. Plazas limitadas.", "Recepción con champán\nMenú de Navidad en seis pases\nBodega seleccionada\nMúsica en directo y barra de cócteles"],
        ['Taller de coronas de Navidad', 'Crea tu corona con verdes naturales', 'Taller', 30, '17:00:00', 2, 1, 16, 'draft', [['Plaza en el taller', 75, 16]], 0, "Una tarde para preparar la casa para las fiestas.", "Materiales\nTu corona para llevar\nMerienda"],
    ];
    $names = ['Carmen Ortega', 'Marta Salinas', 'Paula Reina', 'Isabel Luque', 'Cristina Vera', 'Ana Belén Mora', 'Rocío Castillo', 'Elena Pardo', 'Sofía Márquez', 'Beatriz Olmo', 'Laura Quintana', 'Nuria Ferrer', 'Patricia Lozano', 'Alicia Roldán', 'Inés Cabello',
        'Mónica Tejada', 'Teresa Aguilar', 'Raquel Montes', 'Silvia Peña', 'Marina Cuesta', 'Julia Navas', 'Claudia Rey', 'Victoria Sanz', 'Lorena Ibáñez', 'Natalia Cano', 'Eva Molina', 'Irene Calvo', 'Sara Benítez', 'Olga Prieto', 'Pilar Espejo'];
    $liked = ['El ambiente era precioso, todo cuidado al detalle.', 'La comida espectacular y el trato muy cercano.', 'Me encantó la mesa y la música. Repetiré seguro.', 'Un plan diferente para ir con amigas.', 'La decoración y la luz, de revista.', ''];
    $improve = ['', '', '', 'Algo más de tiempo entre platos.', 'El aparcamiento estaba un poco justo.', 'Que avisen antes de la hora exacta de inicio.'];
    $evId = []; $pdo = db();
    foreach ($events as $n => $E) {
        $start = $day($E[3], $E[4]);
        $id = insert('events', ['slug' => slugify($E[0]), 'title' => $E[0], 'subtitle' => $E[1], 'category' => $E[2], 'starts_at' => $start, 'ends_at' => date('Y-m-d H:i:s', strtotime($start) + 3 * 3600), 'venue_id' => $venue[$E[5]], 'partner_id' => $partner[$E[6]],
            'manager_id' => $admin, 'capacity' => $E[7], 'status' => $E[8], 'tax_pct' => 21, 'description' => $E[11], 'includes' => $E[12], 'partner_fee' => $n === 1 ? 300 : 0, 'created_at' => $day($E[3] - 40)]);
        $evId[] = $id; $tts = [];
        foreach ($E[9] as $i => $t) $tts[] = insert('ticket_types', ['event_id' => $id, 'name' => $t[0], 'price' => $t[1], 'quota' => $t[2], 'seats' => $t[3] ?? 1, 'max_per_order' => 6, 'active' => 1, 'sort' => $i]);
        $ev = row('SELECT * FROM events WHERE id=?', [$id]); $sold = 0; $guard = 0;
        while ($sold < $E[10] && $guard++ < 200) {
            $second = count($tts) > 1 && mt_rand(1, 5) === 1; $qty = $second ? 1 : min($E[10] - $sold, $pick([1 => 3, 2 => 5, 3 => 1, 4 => 1]));
            $seats = $second ? $E[9][1][3] : $qty;
            if ($sold + $seats > $E[10]) { $second = false; $qty = min($qty, $E[10] - $sold); $seats = $qty; }
            $who = $names[mt_rand(0, count($names) - 1)]; $src = $pick(['instagram' => 55, '' => 20, 'whatsapp' => 10, 'facebook' => 8, 'newsletter' => 7]);
            try {
                $o = create_order($ev, [$tts[$second ? 1 : 0] => $qty], ['name' => $who, 'email' => slugify($who) . '@ejemplo.test', 'phone' => '6' . mt_rand(10000000, 99999999), 'marketing_ok' => mt_rand(0, 2) > 0],
                    $pick(['transfer' => 3, 'bizum' => 5, 'stripe' => 6]), '', ['admin' => true, 'paid' => true, 'utm' => ['source' => $src, 'medium' => $src ? 'social' : '', 'campaign' => $src ? $ev['slug'] : '']]);
            } catch (RuntimeException $x) { break; }
            $when = date('Y-m-d H:i:s', $E[3] > 0 ? time() - mt_rand(1, 25) * 86400 - mt_rand(0, 80000) : strtotime($start) - mt_rand(2, 26) * 86400 + mt_rand(0, 80000));
            q('UPDATE orders SET created_by=NULL, created_at=?, paid_at=?, pay_ref=? WHERE id=?', [$when, $when, '', $o['id']]);
            q('UPDATE invoices_out SET issued_at=? WHERE order_id=?', [substr($when, 0, 10), $o['id']]);
            // cada linea de pedido cuenta como una entrada, aunque sea mesa o grupo
            $sold += $seats;
            if ($E[8] === 'done') {
                if (mt_rand(1, 100) <= 93) q("UPDATE tickets SET status='used', checked_in_at=?, checked_in_by=? WHERE order_id=?", [date('Y-m-d H:i:s', strtotime($start) + mt_rand(-900, 1500)), $admin, $o['id']]);
                if (mt_rand(1, 100) <= 55) { $r = $pick([5 => 12, 4 => 6, 3 => 2]); insert('feedback', ['event_id' => $id, 'order_id' => $o['id'], 'rating' => $r, 'nps' => $r === 5 ? mt_rand(9, 10) : ($r === 4 ? mt_rand(7, 9) : mt_rand(4, 7)), 'liked' => $liked[mt_rand(0, 5)], 'improve' => $improve[mt_rand(0, 5)], 'created_at' => date('Y-m-d H:i:s', strtotime($start) + mt_rand(1, 4) * 86400)]); q('UPDATE orders SET survey_sent=1 WHERE id=?', [$o['id']]); }
            }
        }
        // Montaje, personal y tareas
        $big = $E[7] >= 30;
        $setup = $big ? [['Mesas y sillas', 'Mesa imperial de 3 m', (int)ceil($E[7] / 10), 45, 0], ['Mesas y sillas', 'Silla crossback de madera', $E[7], 4.5, 0], ['Mantelería y vestido de mesas', 'Mantel de lino natural', (int)ceil($E[7] / 10), 14, 1], ['Mantelería y vestido de mesas', 'Servilleta de lino', $E[7], 1.2, 1],
            ['Menaje y cristalería', 'Juego de copas y vajilla por comensal', $E[7], 3.8, 0], ['Decoración floral', 'Centro de mesa de temporada', (int)ceil($E[7] / 5), 38, 2], ['Decoración', 'Velas y portavelas', 120, 0.9, 2], ['Sonido', 'Equipo de sonido y microfonía', 1, 280, 3]]
            : [['Mesas y sillas', 'Mesa de trabajo', (int)ceil($E[7] / 8), 30, 0], ['Mantelería y vestido de mesas', 'Camino de mesa de lino', (int)ceil($E[7] / 8), 9, 1], ['Decoración floral', 'Flor de temporada para la sala', 1, 120, 2], ['Cartelería', 'Minutas y tarjetas de sitio', $E[7], 1.1, 0]];
        $sts = $E[8] === 'done' ? ['recogido'] : ($E[3] < 15 ? ['confirmado', 'confirmado', 'pedido', 'pendiente'] : ['pendiente', 'pedido', 'pendiente']);
        foreach ($setup as $i => $s) insert('setup_items', ['event_id' => $id, 'category' => $s[0], 'name' => $s[1], 'qty' => $s[2], 'unit_cost' => $s[3], 'supplier_id' => $sup[$s[4]], 'status' => $sts[$i % count($sts)], 'due_at' => date('Y-m-d H:i:s', strtotime($start) - 5 * 3600)]);
        $staff = $big ? [[5, 'Camarero de sala', 105], [6, 'Camarera de sala', 105], [4, 'Fotografía del evento', 250]] : [[6, 'Apoyo en sala', 60], [4, 'Fotografía del evento', 180]];
        foreach ($staff as $s) insert('staff_assignments', ['event_id' => $id, 'supplier_id' => $sup[$s[0]], 'job' => $s[1], 'start_at' => date('Y-m-d H:i:s', strtotime($start) - 2 * 3600), 'end_at' => date('Y-m-d H:i:s', strtotime($start) + 4 * 3600), 'cost' => $s[2], 'status' => $E[8] === 'done' ? 'realizado' : ($E[3] < 15 ? 'confirmado' : 'propuesto')]);
        foreach (TASK_TEMPLATE as $t) { $due = strtotime($start) + $t[2] * 86400; $done = $E[8] === 'done' || $due < time() - 86400 * 2 ? 1 : 0; insert('tasks', ['event_id' => $id, 'phase' => $t[0], 'title' => $t[1], 'due_date' => date('Y-m-d', $due), 'assignee_id' => $admin, 'done' => $done, 'done_at' => $done ? date('Y-m-d H:i:s', $due) : null]); }
    }

    // Facturas de proveedores y pagos de los eventos ya celebrados
    $inv = function ($s, $e, $num, $concept, $base, $issued, $due, $paid) use ($sup, $evId, $admin) {
        $total = round($base * 1.21, 2);
        $id = insert('supplier_invoices', ['supplier_id' => $sup[$s], 'event_id' => $evId[$e], 'num' => $num, 'concept' => $concept, 'issued_at' => $issued, 'due_at' => $due, 'base' => $base, 'tax_pct' => 21, 'total' => $total, 'paid' => $paid ? $total : 0, 'status' => $paid ? 'paid' : 'pending', 'created_at' => now()]);
        if ($paid) insert('payments', ['kind' => 'supplier', 'supplier_invoice_id' => $id, 'amount' => $total, 'paid_at' => $due, 'method' => 'transfer', 'user_id' => $admin, 'created_at' => now()]);
    };
    $d = fn($n) => date('Y-m-d', time() + $n * 86400);
    $inv(0, 1, 'A-2231', 'Mobiliario y menaje cena a la luz de las velas', 512, $d(-19), $d(-4), true);
    $inv(1, 1, '2026/0418', 'Mantelería de lino', 104, $d(-19), $d(-5), true);
    $inv(2, 1, 'F-0912', 'Centros de mesa y velas', 412, $d(-18), $d(4), false);
    $inv(3, 1, 'SB-771', 'Sonido y microfonía', 280, $d(-18), $d(-2), false);
    $inv(2, 0, 'F-0877', 'Flor de temporada taller', 120, $d(-43), $d(-28), true);
    $inv(0, 0, 'A-2198', 'Mesas de trabajo y cartelería', 78, $d(-43), $d(-28), true);
    $ev = row('SELECT * FROM events WHERE id=?', [$evId[0]]);
    $gross = round((float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE event_id=? AND status='paid'", [$evId[0]]) / 1.21, 2); $amount = round($gross * 0.6, 2);
    $sid = insert('settlements', ['partner_id' => $partner[1], 'event_id' => $evId[0], 'gross' => $gross, 'share_pct' => 60, 'fee' => 0, 'amount' => $amount, 'paid' => $amount, 'status' => 'paid', 'created_at' => $d(-40) . ' 10:00:00']);
    insert('payments', ['kind' => 'partner', 'settlement_id' => $sid, 'amount' => $amount, 'paid_at' => $d(-38), 'method' => 'transfer', 'user_id' => $admin, 'created_at' => now()]);

    // Redes sociales
    $handle = '@' . str_replace('-', '', slugify(brand()));
    $ig = insert('social_accounts', ['platform' => 'instagram', 'handle' => $handle, 'url' => 'https://www.instagram.com/' . ltrim($handle, '@'), 'active' => 1]);
    $fbk = insert('social_accounts', ['platform' => 'facebook', 'handle' => brand(), 'active' => 1]);
    $wa = insert('social_accounts', ['platform' => 'whatsapp', 'handle' => 'Canal de novedades', 'active' => 1]);
    $f1 = 2140; $f2 = 610;
    for ($wk = 12; $wk >= 0; $wk--) {
        $f1 += mt_rand(60, 150); $f2 += mt_rand(2, 14);
        insert('social_metrics', ['account_id' => $ig, 'day' => $d(-$wk * 7), 'followers' => $f1, 'reach' => mt_rand(4200, 9800), 'impressions' => mt_rand(9000, 21000), 'profile_visits' => mt_rand(380, 900), 'link_clicks' => mt_rand(60, 210)]);
        insert('social_metrics', ['account_id' => $fbk, 'day' => $d(-$wk * 7), 'followers' => $f2, 'reach' => mt_rand(300, 900), 'impressions' => mt_rand(500, 1500), 'profile_visits' => mt_rand(20, 70), 'link_clicks' => mt_rand(4, 22)]);
    }
    $posts = [[$ig, 1, 'Anuncio: cena a la luz de las velas', 'Reel', -46, 8400, 612, 48, 95, 140, 188], [$ig, 1, 'Así montamos la mesa imperial', 'Carrusel', -38, 5200, 430, 31, 40, 210, 96], [$ig, 1, 'Últimas plazas para la cena', 'Historia', -24, 2100, 88, 6, 12, 9, 74],
        [$ig, 1, 'Lo que pasó en la cena', 'Reel', -17, 11800, 940, 87, 160, 230, 121], [$ig, 0, 'Taller floral: plazas abiertas', 'Publicación', -62, 3900, 301, 22, 18, 77, 64], [$fbk, 1, 'Cena a la luz de las velas', 'Publicación', -45, 640, 31, 4, 6, 2, 18],
        [$ig, 2, 'Brunch entre amigas: ya a la venta', 'Reel', -12, 7600, 548, 52, 110, 131, 164], [$ig, 2, 'Qué incluye el brunch', 'Carrusel', -6, 4300, 366, 19, 28, 148, 83], [$wa, 2, 'Aviso al canal: brunch', 'Mensaje o email', -11, 410, 0, 0, 0, 0, 58],
        [$ig, 3, 'Presentamos la cata de autor', 'Publicación', -4, 3100, 240, 15, 22, 61, 47]];
    foreach ($posts as $p) insert('social_posts', ['account_id' => $p[0], 'event_id' => $evId[$p[1]], 'title' => $p[2], 'format' => $p[3], 'status' => 'publicado', 'scheduled_at' => $d($p[4]) . ' 19:00:00', 'reach' => $p[5], 'impressions' => (int)($p[5] * 1.6), 'likes' => $p[6], 'comments' => $p[7], 'shares' => $p[8], 'saves' => $p[9], 'clicks' => $p[10], 'utm_campaign' => slugify($events[$p[1]][0])]);
    foreach ([[$ig, 2, 'Recordatorio en stories: quedan pocas plazas', 'Historia', 3, 'programado'], [$ig, 4, 'Anuncio de la cena de Navidad', 'Reel', 5, 'borrador'], [$ig, 3, 'Conoce al sumiller', 'Carrusel', 8, 'idea'], [$fbk, 4, 'Cena de Navidad: reserva tu mesa', 'Publicación', 6, 'programado']] as $p)
        insert('social_posts', ['account_id' => $p[0], 'event_id' => $evId[$p[1]], 'title' => $p[2], 'format' => $p[3], 'status' => $p[5], 'scheduled_at' => $d($p[4]) . ' 19:00:00', 'utm_campaign' => slugify($events[$p[1]][0])]);
    insert('discount_codes', ['code' => 'AMIGAS10', 'kind' => 'pct', 'amount' => 10, 'max_uses' => 50, 'active' => 1]);
    insert('discount_codes', ['code' => 'BIENVENIDA', 'kind' => 'fixed', 'amount' => 5, 'active' => 1, 'valid_until' => $d(60)]);

    // Visitas a la web de los ultimos 75 dias
    $pdo->beginTransaction();
    for ($n = 74; $n >= 0; $n--) {
        $dd = $d(-$n); $visitors = mt_rand(14, 34) + (int)((74 - $n) / 5) + ((int)date('N', strtotime($dd)) >= 5 ? 9 : 0);
        for ($v = 0; $v < $visitors; $v++) {
            $vid = substr(sha1($dd . $v), 0, 16); $src = $pick(['instagram' => 58, '' => 17, 'google' => 9, 'whatsapp' => 7, 'facebook' => 5, 'newsletter' => 4]); $dev = $pick(['Móvil' => 78, 'Ordenador' => 17, 'Tablet' => 5]);
            $pv = fn($path, $eid) => insert('pageviews', ['day' => $dd, 'created_at' => $dd . ' 12:00:00', 'kind' => 'view', 'path' => $path, 'event_id' => $eid, 'visitor' => $vid, 'utm_source' => in_array($src, ['google', '']) ? '' : $src, 'referrer' => $src === 'google' ? 'google.com' : '', 'utm_medium' => $src ? 'social' : '', 'device' => $dev]);
            if (mt_rand(1, 10) <= 6) $pv('inicio', null);
            if (mt_rand(1, 10) <= 7) {
                $live = []; foreach ($events as $i => $E) if ($E[8] !== 'draft' && $E[3] >= -$n && $E[3] - 40 <= -$n) $live[] = $i;
                if ($live) { $i = $live[mt_rand(0, count($live) - 1)]; $pv('evento/' . slugify($events[$i][0]), $evId[$i]); }
            }
        }
    }
    $pdo->commit();
    unset($GLOBALS['NO_MAIL']);
}
