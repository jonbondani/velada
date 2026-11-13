<?php
need('suppliers');
crud_page('Proveedores y personal', [
    'table' => 'suppliers', 'route' => 'admin/suppliers', 'perm' => 'suppliers', 'singular' => 'proveedor', 'new' => 'Añadir', 'order' => 'active DESC, name', 'search' => ['name', 'contact', 'category'], 'filters' => ['category', 'kind'],
    'intro' => 'Empresas y personas que contratas para los eventos: catering, mobiliario, floristería, camareros, fotografía…',
    'fields' => [
        'name' => ['label' => 'Nombre', 'req' => 1, 'list' => 1],
        'kind' => ['label' => 'Tipo', 'type' => 'select', 'req' => 1, 'options' => ['empresa' => 'Empresa proveedora', 'persona' => 'Personal (autónomo o eventual)'], 'default' => 'empresa', 'list' => 1],
        'category' => ['label' => 'Categoría', 'type' => 'select', 'options' => fn() => opts(SUP_CATS), 'list' => 1],
        'rate' => ['label' => 'Tarifa habitual', 'ph' => '15 €/hora, 300 € por evento…', 'list' => 1],
        'contact' => ['label' => 'Persona de contacto', 'section' => 'Contacto'],
        'phone' => ['label' => 'Teléfono', 'type' => 'tel', 'list' => 1],
        'email' => ['label' => 'Correo', 'type' => 'email'],
        'tax_id' => ['label' => 'NIF / CIF', 'section' => 'Datos de facturación'],
        'iban' => ['label' => 'IBAN'],
        'notes' => ['label' => 'Notas internas', 'type' => 'textarea'],
        'active' => ['label' => 'Activo', 'type' => 'check', 'default' => 1],
    ],
    'before_delete' => function ($r) { if (val('SELECT COUNT(*) FROM supplier_invoices WHERE supplier_id=?', [$r['id']])) throw new RuntimeException('Tiene facturas registradas. Desmarca "Activo" en lugar de eliminarlo.'); },
]);
