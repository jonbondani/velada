<?php
need('events');
$u = user(); $isPartner = $u['role'] === 'partner';
$c = [
    'table' => 'events', 'route' => 'admin/events', 'perm' => 'events', 'singular' => 'evento', 'new' => 'Nuevo evento', 'order' => "CASE WHEN status IN ('done','cancelled') THEN 1 ELSE 0 END, starts_at", 'search' => ['title', 'subtitle'], 'filters' => ['status'],
    'empty' => 'Crea tu primer evento. Después podrás añadir entradas, montaje, tareas y promoción.',
    'row_url' => fn($r) => url('admin/event', ['id' => $r['id']]),
    'after' => fn($id) => url('admin/event', ['id' => $id]),
    'fields' => [
        'title' => ['label' => 'Nombre del evento', 'req' => 1, 'list' => 1, 'short' => 'Evento', 'full' => 1],
        'subtitle' => ['label' => 'Frase corta de presentación', 'full' => 1, 'ph' => 'Una línea que apetezca leer'],
        'starts_at' => ['label' => 'Empieza', 'type' => 'datetime', 'req' => 1, 'list' => 1, 'short' => 'Fecha'],
        'ends_at' => ['label' => 'Termina', 'type' => 'datetime'],
        'category' => ['label' => 'Tipo de evento', 'type' => 'select', 'options' => fn() => opts(EVENT_CATS)],
        'status' => ['label' => 'Estado', 'type' => 'select', 'req' => 1, 'options' => EVENT_STATUS, 'default' => 'draft', 'list' => 1, 'badge' => EVENT_BADGE, 'help' => 'Solo los eventos publicados aparecen en la web.'],
        'venue_id' => ['label' => 'Local', 'type' => 'select', 'options' => fn() => pairs('SELECT id, name FROM venues WHERE active=1 ORDER BY name'), 'section' => 'Dónde y con quién', 'list' => 1],
        'partner_id' => ['label' => 'Partner que lo realiza', 'type' => 'select', 'options' => fn() => pairs('SELECT id, name FROM partners WHERE active=1 ORDER BY name'), 'list' => 1, 'short' => 'Partner'],
        'manager_id' => ['label' => 'Responsable interno', 'type' => 'select', 'options' => fn() => pairs("SELECT id, name FROM users WHERE active=1 AND role<>'partner' ORDER BY name")],
        'capacity' => ['label' => 'Aforo total', 'type' => 'number', 'help' => 'Plazas máximas a la venta. 0 = sin límite.'],
        'image' => ['label' => 'Foto de portada', 'type' => 'image', 'section' => 'Lo que verán las clientas', 'help' => 'Horizontal, de buena calidad. JPG, PNG o WebP hasta 8 MB.', 'full' => 1],
        'description' => ['label' => 'Descripción', 'type' => 'textarea', 'rows' => 7],
        'includes' => ['label' => 'Qué incluye (una línea por elemento)', 'type' => 'textarea', 'rows' => 5, 'ph' => "Menú degustación de cinco pases\nMaridaje\nDetalle de bienvenida"],
        'tax_pct' => ['label' => 'IVA incluido en el precio', 'type' => 'pct', 'section' => 'Economía', 'default' => setting('tax_pct', '21')],
        'partner_share_pct' => ['label' => 'Porcentaje de venta para el partner', 'type' => 'pct', 'help' => 'Si lo dejas a 0 se usa el porcentaje habitual del partner.'],
        'partner_fee' => ['label' => 'Importe fijo para el partner', 'type' => 'money', 'help' => 'Se suma al porcentaje, si lo hay.'],
        'notes' => ['label' => 'Notas internas', 'type' => 'textarea'],
    ],
    'cols' => ['Vendidas' => function ($r) { $s = event_stats($r['id']); return '<span class="n">' . $s['sold'] . ((int)$r['capacity'] ? ' / ' . (int)$r['capacity'] : '') . '</span>'; }],
    'validate' => function (array &$d, $rec) {
        if (!strtotime(str_replace('T', ' ', $d['starts_at']))) return 'La fecha de inicio no es válida.';
        if ($d['ends_at'] !== '' && $d['ends_at'] < $d['starts_at']) return 'La hora de fin no puede ser anterior al inicio.';
        if (!$rec || $rec['title'] !== $d['title']) {
            $base = slugify($d['title']); $slug = $base; $i = 2;
            while (val('SELECT COUNT(*) FROM events WHERE slug=? AND id<>?', [$slug, $rec['id'] ?? 0])) $slug = $base . '-' . $i++;
            $d['slug'] = $slug;
        }
        return null;
    },
    'after_save' => fn($id, $d, $new) => log_activity($new ? 'Evento creado' : 'Evento modificado', $d['title']),
    'before_delete' => function ($r) {
        if (val('SELECT COUNT(*) FROM orders WHERE event_id=?', [$r['id']])) throw new RuntimeException('Este evento ya tiene reservas, no se puede eliminar. Puedes marcarlo como cancelado.');
        foreach (['ticket_types', 'setup_items', 'tasks', 'staff_assignments'] as $t) q("DELETE FROM $t WHERE event_id=?", [$r['id']]);
    },
];
if ($isPartner) { $c['where'] = ['partner_id=?', [(int)$u['partner_id']]]; $c['readonly'] = true; }
crud_page($isPartner ? 'Mis eventos' : 'Eventos', $c);
