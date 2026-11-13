<?php
need('venues');
crud_page('Locales', [
    'table' => 'venues', 'route' => 'admin/venues', 'perm' => 'venues', 'singular' => 'local', 'new' => 'Añadir local', 'order' => 'active DESC, name', 'search' => ['name', 'city', 'address'],
    'empty' => 'Añade los espacios donde celebras los eventos: restaurantes, fincas, terrazas, salas privadas.',
    'fields' => [
        'name' => ['label' => 'Nombre del local', 'req' => 1, 'list' => 1],
        'city' => ['label' => 'Ciudad', 'list' => 1],
        'address' => ['label' => 'Dirección', 'full' => 1],
        'capacity' => ['label' => 'Aforo máximo', 'type' => 'number', 'list' => 1, 'short' => 'Aforo'],
        'rental_cost' => ['label' => 'Coste de alquiler por evento', 'type' => 'money', 'list' => 1, 'short' => 'Alquiler'],
        'contact' => ['label' => 'Persona de contacto', 'section' => 'Contacto', 'list' => 1],
        'phone' => ['label' => 'Teléfono', 'type' => 'tel'],
        'email' => ['label' => 'Correo', 'type' => 'email'],
        'maps_url' => ['label' => 'Enlace a Google Maps', 'type' => 'url'],
        'features' => ['label' => 'Equipamiento disponible', 'type' => 'textarea', 'section' => 'Detalles', 'help' => 'Mesas, sillas, cocina, sonido, aparcamiento, accesibilidad, horario de carga y descarga…'],
        'notes' => ['label' => 'Notas internas', 'type' => 'textarea'],
        'active' => ['label' => 'Disponible para nuevos eventos', 'type' => 'check', 'default' => 1],
    ],
    'cols' => ['Eventos' => fn($r) => (int)val('SELECT COUNT(*) FROM events WHERE venue_id=?', [$r['id']])],
    'before_delete' => function ($r) { if (val('SELECT COUNT(*) FROM events WHERE venue_id=?', [$r['id']])) throw new RuntimeException('Este local tiene eventos asociados. Desmarca "Disponible" en lugar de eliminarlo.'); },
]);
