<?php
need('partners');
crud_page('Partners', [
    'table' => 'partners', 'route' => 'admin/partners', 'perm' => 'partners', 'singular' => 'partner', 'new' => 'Añadir partner', 'order' => 'active DESC, name', 'search' => ['name', 'contact', 'speciality'],
    'intro' => 'Los partners son quienes realizan cada evento: chefs, formadoras, marcas, sumilleres… Aquí se define cómo se reparte la venta con cada uno.',
    'fields' => [
        'name' => ['label' => 'Nombre o marca', 'req' => 1, 'list' => 1],
        'speciality' => ['label' => 'Especialidad', 'list' => 1, 'ph' => 'Chef, florista, sumiller…'],
        'contact' => ['label' => 'Persona de contacto', 'list' => 1],
        'phone' => ['label' => 'Teléfono', 'type' => 'tel'],
        'email' => ['label' => 'Correo', 'type' => 'email'],
        'instagram' => ['label' => 'Instagram', 'ph' => '@usuario'],
        'share_pct' => ['label' => 'Porcentaje de la venta para el partner', 'type' => 'pct', 'list' => 1, 'short' => 'Reparto', 'section' => 'Acuerdo económico', 'help' => 'Valor habitual. Se puede cambiar en cada evento.'],
        'tax_id' => ['label' => 'NIF / CIF'],
        'iban' => ['label' => 'IBAN para liquidaciones', 'full' => 1],
        'notes' => ['label' => 'Notas internas', 'type' => 'textarea'],
        'active' => ['label' => 'Partner activo', 'type' => 'check', 'default' => 1],
    ],
    'cols' => ['Eventos' => fn($r) => (int)val('SELECT COUNT(*) FROM events WHERE partner_id=?', [$r['id']])],
    'before_delete' => function ($r) { if (val('SELECT COUNT(*) FROM events WHERE partner_id=?', [$r['id']])) throw new RuntimeException('Este partner tiene eventos asociados. Desmarca "Partner activo" en lugar de eliminarlo.'); },
]);
