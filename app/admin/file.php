<?php
// Descarga de documentos privados (facturas de proveedores)
need('finance');
$f = basename((string)($_GET['f'] ?? '')); $p = ROOT . '/storage/files/' . $f;
if ($f === '' || !preg_match('/^[a-f0-9]+\.(pdf|jpg|png)$/', $f) || !is_file($p)) { http_response_code(404); exit('Documento no encontrado'); }
header('Content-Type: ' . ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][pathinfo($f, PATHINFO_EXTENSION)]);
header('Content-Disposition: inline; filename="' . $f . '"'); header('Content-Length: ' . filesize($p));
readfile($p);
