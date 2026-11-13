<?php
// Ayuda: tutoriales guiados que se pueden repetir en cualquier momento
if (is_post()) { q('UPDATE users SET tour_done=1 WHERE id=?', [user()['id']]); header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
require_once ROOT . '/app/tours.php';
$h = '<p class="intro">Elige un tutorial y te guiamos por las pantallas paso a paso, señalando cada botón. No cambia ni guarda nada, así que puedes repetirlos las veces que quieras.</p><div class="tourlist">';
foreach (tours() as $id => $t) $h .= '<div class="card panel"><div><h2>' . e($t['title']) . '</h2><p class="mute">' . e($t['desc']) . ' ' . count($t['steps']) . ' pasos.</p></div><button type="button" class="btn ' . ($id === 'general' ? 'primary' : '') . '" data-tour="' . e($id) . '">Empezar</button></div>';
$h .= '</div>';
$faq = [
    'Una clienta dice que ha pagado por Bizum. ¿Qué hago?' => 'Abre Ventas, busca su reserva y pulsa "Confirmar pago recibido". Recibirá sus entradas por correo al momento.',
    'No le ha llegado el correo con las entradas' => 'Abre su reserva y pulsa "Reenviar entradas por correo". También puedes copiar el enlace "Ver lo que ve ella" y enviárselo por WhatsApp.',
    'Quiero vender una entrada en persona o regalar una invitación' => 'En Ventas, pulsa "Nueva venta", elige el evento y la forma de pago. Para una invitación elige "Invitación (sin coste)".',
    'Una entrada es para dos personas o para una mesa' => 'En la entrada, indica en "Personas por entrada" cuántas plazas ocupa. Cada persona recibe su propio QR.',
    'El evento se ha llenado y quiero ampliar plazas' => 'Edita la ficha del evento y sube el aforo. La web vuelve a vender sola.',
    'No puedo leer un QR en la puerta' => 'Escribe el código que aparece bajo el QR, o busca a la asistente por nombre en la lista y pulsa "Dar entrada".',
    'He olvidado mi contraseña' => 'Pide a administración que te asigne una nueva desde Usuarios.',
];
$f = '';
foreach ($faq as $qn => $a) $f .= '<details><summary>' . e($qn) . '</summary><p>' . e($a) . '</p></details>';
admin_page('Ayuda y tutorial', $h . panel('Dudas frecuentes', $f));
