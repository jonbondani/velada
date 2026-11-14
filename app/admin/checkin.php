<?php
// Control de acceso: lectura de entradas QR con la camara del movil o introduciendo el codigo
need('checkin');
$w = can('checkin', 'w');
$evs = rows("SELECT id, title, starts_at FROM events WHERE status IN ('published','closed','done') AND starts_at>=? ORDER BY starts_at", [date('Y-m-d 00:00:00', time() - 2 * 86400)]);
$evId = (int)($_GET['ev'] ?? 0);
$code0 = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['code'] ?? '')));
if ($code0 && !$evId) $evId = (int)val('SELECT event_id FROM tickets WHERE code=?', [$code0]);
if (!$evId && $evs) $evId = (int)$evs[0]['id'];
$ev = $evId ? row('SELECT * FROM events WHERE id=?', [$evId]) : null;
$counts = fn($e) => checkin_counts($e);

if (is_post()) {
    need('checkin', 'w');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(checkin_scan($_POST['code'] ?? '', $evId, ($_GET['do'] ?? '') === 'undo')); exit;
}

$h = get_form('admin/checkin', [], 'toolbar') . '<select name="ev" data-auto>';
foreach ($evs as $e1) $h .= '<option value="' . $e1['id'] . '"' . ($e1['id'] == $evId ? ' selected' : '') . '>' . e($e1['title'] . ' · ' . fdate($e1['starts_at'], 'd/m H:i')) . '</option>';
$h .= '</select></form>';
if (!$ev) { admin_page('Accesos QR', '<div class="empty"><b>No hay eventos próximos con entradas.</b><br>Aquí podrás validar las entradas el día del evento.</div>'); return; }
$c = $counts($evId);
$h .= '<div class="checkin"><div class="card scan"><div class="counter"><b id="cin">' . $c['in'] . '</b><span>de <span id="call">' . $c['all'] . '</span> han entrado</span></div>'
    . '<div id="result" class="result idle"><b>Listo para leer</b><span>Apunta la cámara al código QR de la entrada</span></div>'
    . ($w ? '<video id="cam" playsinline muted></video><canvas id="cv" hidden></canvas><div class="btnrow center"><button class="btn primary" id="startcam" type="button">Activar cámara</button></div>'
        . '<form id="manual" class="manual"><input name="code" id="code" placeholder="Código de la entrada" autocomplete="off" autocapitalize="characters"><button class="btn">Validar</button></form>' : '<p class="mute center">Tu perfil solo permite consultar.</p>')
    . '</div><div class="card table"><div class="pad"><input type="search" id="filter" placeholder="Buscar asistente por nombre…"></div><table id="att"><tbody>';
foreach (rows("SELECT * FROM tickets WHERE event_id=? AND status<>'void' ORDER BY holder_name, id", [$evId]) as $t)
    $h .= '<tr data-code="' . e($t['code']) . '" class="' . ($t['status'] === 'used' ? 'in' : '') . '"><td><b>' . e($t['holder_name']) . '</b><br><span class="mute">' . e($t['type_name']) . '</span></td><td class="r">'
        . ($w ? '<button type="button" class="btn sm act">' . ($t['status'] === 'used' ? 'Deshacer' : 'Dar entrada') . '</button>' : ($t['status'] === 'used' ? badge('Ha entrado', 'green') : badge('Pendiente'))) . '</td></tr>';
$h .= '</tbody></table></div></div>';
$h .= '<script>window.CHECKIN={url:' . json_encode(url('admin/checkin', ['ev' => $evId])) . ',code:' . json_encode($code0) . ',jsqr:' . json_encode(asset('jsqr.js')) . '};</script>';
admin_page('Accesos QR', $h);
