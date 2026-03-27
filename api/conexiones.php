<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
require 'db.php';
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM conexiones')->fetchAll();
    foreach ($rows as &$r) { $r['propiedades'] = json_decode($r['propiedades'] ?? '{}', true); }
    jsonResponse($rows);
}

if ($method === 'POST') {
    $body = getBody();
    $stmt = $db->prepare('INSERT INTO conexiones (origen_id, destino_id, tipo, label, color, activo, propiedades) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $body['origen_id'], $body['destino_id'],
        $body['tipo'] ?? 'ethernet',
        $body['label'] ?? '',
        $body['color'] ?? '#4488ff',
        isset($body['activo']) ? (int)$body['activo'] : 1,
        json_encode($body['propiedades'] ?? new stdClass)
    ]);
    jsonResponse(['id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $body = getBody();
    $fields = []; $params = [];
    foreach (['tipo','label','color','activo'] as $f) {
        if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (isset($body['propiedades'])) { $fields[] = 'propiedades = ?'; $params[] = json_encode($body['propiedades']); }
    if (!$fields) jsonResponse(['error' => 'Nada que actualizar'], 400);
    $params[] = $id;
    $db->prepare('UPDATE conexiones SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    jsonResponse(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $db->prepare('DELETE FROM conexiones WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
