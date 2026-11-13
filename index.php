<?php
// Punto de entrada unico: todas las direcciones pasan por aqui
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/(assets|uploads)/[^/]+$#', $p) && is_file(__DIR__ . $p) && !preg_match('/\.php$/i', $p)) return false;
}
require __DIR__ . '/app/core.php';
if (!is_array($CONFIG)) { require ROOT . '/app/install.php'; exit; }
require ROOT . '/app/ui.php';
require ROOT . '/app/logic.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

set_exception_handler(function (Throwable $e) {
    @file_put_contents(ROOT . '/storage/error.log', '[' . date('c') . '] ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><div style="font:17px/1.5 -apple-system,Helvetica,Arial,sans-serif;max-width:480px;margin:18vh auto;padding:0 24px;text-align:center;color:#1d1d1f"><h1 style="font-size:26px">Algo no ha ido bien</h1><p style="color:#6e6e73">Ha ocurrido un error inesperado. Vuelve atrás e inténtalo de nuevo. Si se repite, avisa a administración.</p></div>';
});

$route = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path()));
$route = trim(preg_replace('#^/?index\.php#', '', rawurldecode($route)), '/');
$seg = explode('/', $route);

if ($route === 'ping-rewrite') { exit('pong'); }
if ($route === 'stripe/webhook') { stripe_webhook(); }

start_session();
if (is_post()) check_csrf();
if ($seg[0] === 'admin' || $seg[0] === 'acceso') migrate();

function not_found() {
    http_response_code(404);
    site_page('Página no encontrada', '<section class="wrap narrow center pad"><h1>No encontramos esta página</h1><p class="lead">Puede que el enlace haya cambiado o que el evento ya no esté disponible.</p><p><a class="cta" href="' . e(url('')) . '">Ver próximos eventos</a></p></section>', ['noindex' => true]);
    exit;
}

switch ($seg[0]) {
    case '': require ROOT . '/app/site/home.php'; break;
    case 'evento': require ROOT . '/app/site/event.php'; break;
    case 'comprar': require ROOT . '/app/site/buy.php'; break;
    case 'pedido': require ROOT . '/app/site/order.php'; break;
    case 'opinion': require ROOT . '/app/site/feedback.php'; break;
    case 'suscribir': case 'baja': case 'legal': require ROOT . '/app/site/misc.php'; break;
    case 'c': redirect(url('admin/checkin', ['code' => $seg[1] ?? ''])); break;
    case 'acceso': case 'salir': require ROOT . '/app/admin/login.php'; break;
    case 'admin':
        if (!user()) redirect(url('acceso'));
        $mod = $seg[1] ?? 'dashboard';
        $files = ['dashboard', 'events', 'event', 'orders', 'customers', 'checkin', 'partners', 'venues', 'suppliers', 'finance', 'marketing', 'stats', 'users', 'settings', 'file', 'help'];
        if (!in_array($mod, $files, true)) { http_response_code(404); $MODULE = ''; admin_page('No encontrado', '<div class="empty"><b>Esta sección no existe.</b></div>'); break; }
        $MODULE = ['event' => 'events', 'file' => 'finance'][$mod] ?? $mod;
        require ROOT . '/app/admin/' . $mod . '.php';
        break;
    default: not_found();
}
