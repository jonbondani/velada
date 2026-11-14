<?php
// Genera docs/mcp-referencia.md a partir del propio servidor MCP, para que la documentacion no se quede atras.
// Uso:  php app/cli/mcp-doc.php   (tambien comprueba que no quede ninguna tabla sin clasificar)
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/core.php';
$CONFIG = ['db' => ['driver' => 'sqlite', 'file' => 'x'], 'secret' => 'x'];
require ROOT . '/app/logic.php'; require ROOT . '/app/mcp.php';
$miss = mcp_coverage();
if ($miss) { fwrite(STDERR, "ERROR: tablas sin clasificar en el MCP: " . implode(', ', $miss) . "\nAñádelas a mcp_resources() o a MCP_INTERNAL en app/mcp.php.\n"); exit(1); }
$GLOBALS['ACT_USER'] = ['id' => 1, 'name' => 'x', 'role' => 'admin', 'partner_id' => null];
$perm = fn($m) => MODULES[$m] ?? $m;
$o = "# Referencia del servidor MCP\n\nGenerado automáticamente con `php app/cli/mcp-doc.php` (versión " . APP_VER . "). No editar a mano.\n\n## Recursos\n\nCada recurso está protegido por el mismo permiso que su sección del panel.\n\n| Recurso | Qué es | Sección del panel que da acceso | Se puede modificar |\n| --- | --- | --- | --- |\n";
foreach (mcp_resources() as $n => $r) $o .= "| `$n` | " . preg_replace('/ \(.*$/', '', $r['label']) . ' | ' . $perm($r['perm']) . ' | ' . ($r['write'] ? 'Sí, con permiso de gestión' : 'No, solo consulta') . " |\n";
$o .= "\nNunca se exponen: " . implode(', ', array_map(fn($t) => "`$t`", MCP_INTERNAL)) . ", ni ningún campo cuyo nombre contenga pass, token, secret o hash.\n\n## Herramientas\n\n| Herramienta | Qué hace | Tipo |\n| --- | --- | --- |\n";
foreach (mcp_tools(['readonly' => false]) as $n => $t) $o .= "| `$n` | " . preg_replace('/ Recursos (disponibles para ti|que puedes crear): .*$/', '', $t['description']) . ' | ' . (!empty($t['annotations']['readOnlyHint']) ? 'Consulta' : (!empty($t['annotations']['destructiveHint']) ? 'Cambio delicado' : 'Cambio')) . " |\n";
$o .= "\nCada perfil ve solo las herramientas que su permiso admite. Una conexión de solo consulta no recibe ninguna herramienta de cambio.\n\n## Qué recursos ve cada perfil\n\n| Perfil | Consulta | Además puede modificar |\n| --- | --- | --- |\n";
foreach (ROLES as $rk => $rl) {
    $GLOBALS['ACT_USER'] = ['id' => 1, 'name' => 'x', 'role' => $rk, 'partner_id' => 1]; $m = mcp_my_resources(['readonly' => false]);
    $o .= "| $rl | " . (implode(', ', array_keys($m)) ?: 'Nada') . ' | ' . (implode(', ', array_keys(array_filter($m, fn($r) => $r['w']))) ?: 'Nada') . " |\n";
}
file_put_contents(ROOT . '/docs/mcp-referencia.md', $o);
echo "docs/mcp-referencia.md actualizado. Cobertura completa.\n";
