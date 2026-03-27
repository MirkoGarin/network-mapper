<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
require 'db.php';
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    jsonResponse($db->query('SELECT * FROM capas ORDER BY orden, id')->fetchAll());
}

if ($method === 'POST') {
    $body = getBody();
    if (empty($body['nombre'])) jsonResponse(['error' => 'Nombre requerido'], 400);
    $stmt = $db->prepare('INSERT INTO capas (nombre, color, orden) VALUES (?, ?, ?)');
    $stmt->execute([$body['nombre'], $body['color'] ?? '#4488ff', $body['orden'] ?? 0]);
    jsonResponse(['id' => $db->lastInsertId(), 'nombre' => $body['nombre'], 'color' => $body['color'] ?? '#4488ff', 'visible' => 1]);
}

if ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $body = getBody();
    $fields = []; $params = [];
    foreach (['nombre', 'color', 'visible', 'orden'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (!$fields) jsonResponse(['error' => 'Nada que actualizar'], 400);
    $params[] = $id;
    $db->prepare('UPDATE capas SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    jsonResponse(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $db->prepare('UPDATE elementos SET capa_id = NULL WHERE capa_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM capas WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
