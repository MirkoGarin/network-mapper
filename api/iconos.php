<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
require 'db.php';
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT i.*, c.nombre as categoria FROM iconos i LEFT JOIN categorias c ON i.categoria_id = c.id ORDER BY c.orden, i.nombre')->fetchAll();
    jsonResponse($rows);
}

if ($method === 'POST') {
    $nombre = $_POST['nombre'] ?? 'Sin nombre';
    $categoria_id = $_POST['categoria_id'] ?? null;
    if (empty($_FILES['file']['tmp_name'])) jsonResponse(['error' => 'No se envió archivo'], 400);
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','svg','webp'])) jsonResponse(['error' => 'Formato no permitido'], 400);
    $filename = uniqid() . '.' . $ext;
    $dest = __DIR__ . '/../uploads/iconos/' . $filename;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) jsonResponse(['error' => 'Error al guardar'], 500);
    $stmt = $db->prepare('INSERT INTO iconos (nombre, archivo, categoria_id) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $filename, $categoria_id ?: null]);
    jsonResponse(['id' => $db->lastInsertId(), 'nombre' => $nombre, 'archivo' => $filename]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $row = $db->prepare('SELECT archivo FROM iconos WHERE id = ?');
    $row->execute([$id]);
    $icono = $row->fetch();
    if (!$icono) jsonResponse(['error' => 'No encontrado'], 404);
    $file = __DIR__ . '/../uploads/iconos/' . $icono['archivo'];
    if (file_exists($file)) unlink($file);
    $db->prepare('DELETE FROM iconos WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}
