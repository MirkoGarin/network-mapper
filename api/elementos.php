<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
require 'db.php';
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT e.*, i.nombre as icono_nombre, i.archivo as icono_archivo, c.nombre as capa_nombre, c.color as capa_color FROM elementos e LEFT JOIN iconos i ON e.icono_id = i.id LEFT JOIN capas c ON e.capa_id = c.id')->fetchAll();
    foreach ($rows as &$r) { $r['propiedades'] = json_decode($r['propiedades'] ?? '{}', true); }
    jsonResponse($rows);
}

if ($method === 'POST') {
    $body = getBody();
    $stmt = $db->prepare('INSERT INTO elementos (icono_id, label, x, y, capa_id, propiedades) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$body['icono_id'] ?? null, $body['label'] ?? '', $body['x'], $body['y'], $body['capa_id'] ?? null, json_encode($body['propiedades'] ?? new stdClass)]);
    jsonResponse(['id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $body = getBody();
    $fields = []; $params = [];
    foreach (['label', 'x', 'y', 'capa_id'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (array_key_exists('propiedades', $body)) { $fields[] = 'propiedades = ?'; $params[] = json_encode($body['propiedades']); }
    if (!$fields) jsonResponse(['error' => 'Nada que actualizar'], 400);
    $params[] = $id;
    $db->prepare('UPDATE elementos SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    jsonResponse(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $db->prepare('DELETE FROM elementos WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
